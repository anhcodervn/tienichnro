<?php

namespace App\Features\Admin\Topup\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Admin\Topup\Actions\ReorderFailedTopupOrderAction;
use App\Features\Admin\Topup\Actions\RetryProviderBalanceOrderAction;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Features\Topup\Services\OrderStatusService;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Mail\Orders\OrderCompletedMail;
use App\Mail\Orders\OrderFailedMail;
use App\Mail\Orders\PaymentReceivedMail;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class TopupAdminService
{
    public function __construct(
        private readonly OrderStatusService $orderStatusService,
        private readonly ReorderFailedTopupOrderAction $reorderFailedTopupOrder,
        private readonly RetryProviderBalanceOrderAction $retryProviderBalanceOrder,
        private readonly RecipientFulfillmentService $recipientFulfillmentService,
        private readonly GlobalTopupPackageSyncService $globalPackageSyncService,
        private readonly TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $filters */
    public function games(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Game::query()->withCount(['servers', 'packages'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%");
            }))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPageFromFilters($filters));
    }

    /** @param array<string, mixed> $filters */
    public function servers(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameServer::query()->with('game:id,name')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            }))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPageFromFilters($filters));
    }

    /** @param array<string, mixed> $filters */
    public function providers(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return TopupProvider::query()
            ->withCount('packages')
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($this->perPageFromFilters($filters));
    }

    /** @param array<string, mixed> $filters */
    public function packages(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return TopupPackage::query()->with([
            'game:id,name',
            'provider:id,name,slug',
        ])
            ->whereNull('global_topup_package_id')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['provider_id'] ?? null), fn (Builder $query) => $query->where('provider_id', $filters['provider_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(isset($filters['min_price']), fn (Builder $query) => $query->where('price', '>=', $filters['min_price']))
            ->when(isset($filters['max_price']), fn (Builder $query) => $query->where('price', '<=', $filters['max_price']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPageFromFilters($filters));
    }

    public function orders(Request $request): LengthAwarePaginator
    {
        $isPlatformView = $this->tenantContext->isActive() && $this->tenantContext->isMain();
        $relations = ['game:id,name', 'server:id,name', 'provider:id,name,slug,type', 'latestPaymentTransaction', 'legacyPaymentTransaction'];

        if ($isPlatformView) {
            $relations[] = 'tenant:id,name,slug';
        }

        return $this->orderQuery($request->integer('tenant_id') ?: null)
            ->with($relations)
            ->when($request->filled('search'), function (Builder $query) use ($request, $isPlatformView): void {
                $search = trim($request->string('search')->toString());
                $query->where(fn (Builder $nested) => $nested
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('topup_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('paymentTransactions', function (Builder $paymentQuery) use ($search, $isPlatformView): void {
                        if ($isPlatformView) {
                            $paymentQuery->withoutGlobalScope(TenantScope::class);
                        }

                        $paymentQuery->where(fn (Builder $paymentCodeQuery) => $paymentCodeQuery
                            ->where('transfer_reference', 'like', "%{$search}%")
                            ->orWhere('content', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('legacyPaymentTransaction', fn (Builder $legacyPaymentQuery) => $legacyPaymentQuery
                        ->where(fn (Builder $paymentCodeQuery) => $paymentCodeQuery
                            ->where('transfer_reference', 'like', "%{$search}%")
                            ->orWhere('content', 'like', "%{$search}%"))));
            })
            ->when($request->filled('payment_status'), fn (Builder $query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('order_status'), fn (Builder $query) => $query->where('order_status', $request->string('order_status')))
            ->latest()->paginate($this->perPage($request));
    }

    /**
     * @return array{total: int, pending_payment: int, processing: int, failed: int}
     */
    public function todayCardStatistics(?int $tenantId = null): array
    {
        $startOfToday = now()->startOfDay();
        $startOfTomorrow = $startOfToday->copy()->addDay();
        $statistics = $this->orderQuery($tenantId)
            ->where('created_at', '>=', $startOfToday)
            ->where('created_at', '<', $startOfTomorrow)
            ->selectRaw('COALESCE(SUM(quantity), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_status = ? THEN quantity ELSE 0 END), 0) as pending_payment', [PaymentStatus::Pending->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN quantity ELSE 0 END), 0) as processing', [OrderStatus::Processing->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN quantity ELSE 0 END), 0) as failed', [OrderStatus::Failed->value])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($statistics?->total ?? 0),
            'pending_payment' => (int) ($statistics?->pending_payment ?? 0),
            'processing' => (int) ($statistics?->processing ?? 0),
            'failed' => (int) ($statistics?->failed ?? 0),
        ];
    }

    public function findOrder(string $code): Order
    {
        return $this->orderQuery()->where('code', $code)->firstOrFail();
    }

    /** @param array<string, mixed> $payload */
    public function create(Model $model, array $payload, User $admin, Request $request): Model
    {
        if ($model instanceof TopupPackage) {
            $payload['game_server_id'] = null;
        }

        $model->fill($payload)->save();

        if ($model instanceof Game) {
            $this->globalPackageSyncService->syncGame($model);
        }

        $this->audit($admin, 'created', $model, [], $this->auditSnapshot($model), $request);

        return $model->refresh();
    }

    /** @param array<string, mixed> $payload */
    public function update(Model $model, array $payload, User $admin, Request $request): Model
    {
        $old = $this->auditSnapshot($model);

        if ($model instanceof TopupPackage) {
            $payload['game_server_id'] = null;
        }

        $model->fill($payload)->save();

        if ($model instanceof Game) {
            $this->globalPackageSyncService->syncGame($model);
        }

        $priceChanged = $model instanceof TopupPackage
            && array_intersect(['provider_price', 'original_price', 'price'], array_keys($payload)) !== [];
        $action = $model instanceof TopupProvider && array_key_exists('connection_config', $payload)
            ? 'provider_connection_config_updated'
            : ($priceChanged ? 'package_price_changed' : 'updated');
        $new = $this->auditSnapshot($model);

        if ($model instanceof TopupProvider && array_key_exists('connection_config', $payload)) {
            $new['connection_config_changed'] = true;
        }

        $this->audit($admin, $action, $model, $old, $new, $request);

        return $model->refresh();
    }

    public function disable(Model $model, User $admin, Request $request): Model
    {
        return $this->update($model, ['status' => 'inactive'], $admin, $request);
    }

    public function delete(Model $model, User $admin, Request $request): void
    {
        DB::transaction(function () use ($model, $admin, $request): void {
            $old = $this->auditSnapshot($model);
            $this->audit($admin, 'deleted', $model, $old, [], $request);
            $model->delete();
        }, 3);
    }

    public function deleteCatalogModel(Game|GameServer|TopupPackage $model, User $admin, Request $request): void
    {
        DB::transaction(function () use ($model, $admin, $request): void {
            /** @var Game|GameServer|TopupPackage $lockedModel */
            $lockedModel = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());

            if ($lockedModel instanceof TopupPackage && $lockedModel->global_topup_package_id !== null) {
                throw ValidationException::withMessages([
                    'package' => 'Gói này được đồng bộ tự động từ Gói nạp Global và không thể xóa thủ công.',
                ]);
            }

            [$errorKey, $message, $blockingRelations] = match (true) {
                $lockedModel instanceof Game => [
                    'game',
                    'Không thể xóa game khi còn máy chủ, gói nạp hoặc đơn hàng. Hãy xóa dữ liệu con trước hoặc chuyển game sang Tạm tắt.',
                    ['servers', 'packages', 'orders'],
                ],
                $lockedModel instanceof GameServer => [
                    'server',
                    'Không thể xóa máy chủ khi còn đơn hàng. Hãy chuyển máy chủ sang Tạm tắt để giữ lịch sử.',
                    ['orders'],
                ],
                default => [
                    'package',
                    'Không thể xóa gói nạp đã phát sinh đơn hàng. Hãy chuyển gói sang Tạm tắt để giữ lịch sử.',
                    ['orders'],
                ],
            };

            foreach ($blockingRelations as $relation) {
                if ($lockedModel->{$relation}()->exists()) {
                    throw ValidationException::withMessages([$errorKey => $message]);
                }
            }

            $old = $this->auditSnapshot($lockedModel);
            $this->audit($admin, 'deleted', $lockedModel, $old, [], $request);
            $lockedModel->delete();
        }, 3);
    }

    public function updateOrder(Order $order, string $action, ?string $reason, User $admin, Request $request): Order
    {
        if ($action === 'reorder') {
            return $this->reorderFailedTopupOrder->handle($order, $admin, $request);
        }

        if ($action === 'sync_provider') {
            return $this->syncProviderStatus($order, $admin, $request);
        }

        if ($action === 'retry_provider_submission') {
            $old = $order->getAttributes();
            $order = $this->retryProviderBalanceOrder->handle($order);
            $this->audit($admin, 'order_retry_provider_submission', $order, $old, $order->getAttributes(), $request);
            ProcessTopupOrder::dispatch($order->id)->afterCommit();

            return $order;
        }

        $dispatchTopup = false;

        $order = DB::transaction(function () use ($order, $action, $reason, $admin, $request, &$dispatchTopup): Order {
            $order = $this->orderQuery()->lockForUpdate()->findOrFail($order->id);
            $old = $order->getAttributes();

            match ($action) {
                'mark_paid' => $this->markPaid($order),
                'process' => $this->orderStatusService->transition($order, OrderStatus::Processing),
                'complete' => $this->orderStatusService->transition($order, OrderStatus::Completed),
                'fail' => $this->orderStatusService->transition($order, OrderStatus::Failed, $reason),
                'cancel' => $this->orderStatusService->transition($order, OrderStatus::Cancelled, $reason),
            };

            $dispatchTopup = in_array($action, ['mark_paid', 'process'], true);
            $this->audit($admin, 'order_'.$action, $order, $old, $order->getAttributes(), $request);

            return $order->refresh();
        }, 3);

        if ($action === 'mark_paid') {
            Mail::to($order->email)->queue(new PaymentReceivedMail($order));
        } elseif ($action === 'complete') {
            Mail::to($order->email)->queue(new OrderCompletedMail($order));
        } elseif ($action === 'fail') {
            Mail::to($order->email)->queue(new OrderFailedMail($order));
        }

        if ($dispatchTopup) {
            ProcessTopupOrder::dispatch($order->id)->afterCommit();
        }

        return $order;
    }

    private function syncProviderStatus(Order $order, User $admin, Request $request): Order
    {
        $order->loadMissing('provider');

        if ($order->payment_status !== PaymentStatus::Paid
            || ! in_array($order->order_status, [OrderStatus::Processing, OrderStatus::Completed], true)
            || ! TopupProviderResolver::supportsStatusChecks($order->provider?->type?->value ?? $order->provider?->slug)) {
            throw ValidationException::withMessages([
                'sync_provider' => 'Chỉ có thể kiểm tra đơn provider tự động đã thanh toán, đang xử lý hoặc đã hoàn thành.',
            ]);
        }

        $old = $order->getAttributes();
        $includeCompleted = $order->order_status === OrderStatus::Completed;

        try {
            $tenant = $this->tenantContext->isActive() ? $order->tenant()->first() : null;
            $checked = $tenant instanceof Tenant
                ? $this->tenantContext->run(
                    $tenant,
                    fn (): int => $this->recipientFulfillmentService->syncOrder($order->id, $includeCompleted),
                )
                : $this->recipientFulfillmentService->syncOrder($order->id, $includeCompleted);
        } catch (TopupProviderConnectionException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'sync_provider' => "[{$exception->errorCode}] {$exception->getMessage()}",
            ]);
        }

        $order = $order->refresh();

        if ($checked === 0) {
            throw ValidationException::withMessages([
                'sync_provider' => 'Đơn chưa có giao dịch provider đủ điều kiện để kiểm tra trạng thái.',
            ]);
        }

        $this->audit($admin, 'order_sync_provider', $order, $old, $order->getAttributes(), $request);

        return $order;
    }

    private function markPaid(Order $order): void
    {
        if ($order->payment_status === PaymentStatus::Paid) {
            return;
        }

        $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(User $admin, string $action, Model $subject, array $old, array $new, Request $request): void
    {
        AdminAuditLog::query()->create([
            'admin_id' => $admin->id, 'action' => $action,
            'subject_type' => $subject::class, 'subject_id' => $subject->getKey(),
            'old_values' => $old, 'new_values' => $new,
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(Model $model): array
    {
        if ($model instanceof TopupProvider) {
            return [
                'id' => $model->getKey(),
                'name' => $model->name,
                'slug' => $model->slug,
                'has_connection_config' => filled($model->getRawOriginal('connection_config')),
            ];
        }

        return $model->getAttributes();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 20), 1), 100);
    }

    private function orderQuery(?int $tenantId = null): Builder
    {
        $query = Order::query();

        if ($this->tenantContext->isActive() && $this->tenantContext->isMain()) {
            $query->withoutGlobalScope(TenantScope::class)
                ->when($tenantId !== null, fn (Builder $builder) => $builder->where('tenant_id', $tenantId));
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function perPageFromFilters(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 20), 1), 100);
    }
}
