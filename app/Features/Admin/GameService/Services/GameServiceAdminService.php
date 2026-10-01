<?php

namespace App\Features\Admin\GameService\Services;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Features\Client\GameService\Services\GameServiceOrderService;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderProgress;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\User;
use App\Support\RichTextSanitizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GameServiceAdminService
{
    public function __construct(
        private readonly AffiliateWalletService $affiliateWalletService,
        private readonly GameServiceOrderService $gameServiceOrderService,
        private readonly RichTextSanitizer $richTextSanitizer,
    ) {}

    /** @param array<string, mixed> $filters */
    public function games(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return Game::query()
            ->withCount(['servers', 'gameServices'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('provider_service_code', 'like', "%{$search}%")))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function updateGame(Game $game, array $payload, User $admin, Request $request): Game
    {
        $old = Arr::only($game->getAttributes(), ['provider_service_code', 'game_services_enabled']);
        $game->fill($payload)->save();
        $this->audit($admin, 'game_service_game_updated', $game, $old, Arr::only($game->fresh()->getAttributes(), array_keys($old)), $request);

        return $game->refresh();
    }

    /** @param array<string, mixed> $filters */
    public function services(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameService::query()
            ->with(['game:id,name,slug,provider_service_code', 'servers:id,game_id,name,code'])
            ->withCount(['packages', 'orders'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function createService(array $payload, User $admin, Request $request): GameService
    {
        $payload['description'] = $this->richTextSanitizer->sanitize($payload['description'] ?? null);

        return DB::transaction(function () use ($payload, $admin, $request): GameService {
            $serverIds = Arr::pull($payload, 'server_ids', []);
            $service = GameService::query()->create($payload);
            $service->servers()->sync($serverIds);
            $this->audit($admin, 'created', $service, [], $service->getAttributes(), $request);

            return $this->loadService($service);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateService(GameService $service, array $payload, User $admin, Request $request): GameService
    {
        $payload['description'] = $this->richTextSanitizer->sanitize($payload['description'] ?? null);

        return DB::transaction(function () use ($service, $payload, $admin, $request): GameService {
            $locked = GameService::query()->lockForUpdate()->findOrFail($service->id);
            $old = $locked->getAttributes();
            $serverIds = Arr::pull($payload, 'server_ids', []);
            $locked->fill($payload)->save();
            $locked->servers()->sync($serverIds);
            $this->audit($admin, 'updated', $locked, $old, $locked->fresh()->getAttributes(), $request);

            return $this->loadService($locked);
        }, 3);
    }

    public function deleteService(GameService $service, User $admin, Request $request): void
    {
        DB::transaction(function () use ($service, $admin, $request): void {
            $locked = GameService::query()->lockForUpdate()->findOrFail($service->id);

            if ($locked->orders()->exists()) {
                throw ValidationException::withMessages(['service' => 'Dịch vụ đã có đơn hàng. Hãy chuyển sang Tạm tắt để giữ lịch sử.']);
            }

            $old = $locked->getAttributes();
            $this->audit($admin, 'deleted', $locked, $old, [], $request);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function packages(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameServicePackage::query()
            ->with(['service:id,game_id,name', 'service.game:id,name', 'prices'])
            ->withCount('orders')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->when(filled($filters['game_service_id'] ?? null), fn (Builder $query) => $query->where('game_service_id', $filters['game_service_id']))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->whereHas('service', fn (Builder $serviceQuery) => $serviceQuery->where('game_id', $filters['game_id'])))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($this->perPage($filters));
    }

    /** @param array<string, mixed> $payload */
    public function createPackage(array $payload, User $admin, Request $request): GameServicePackage
    {
        return DB::transaction(function () use ($payload, $admin, $request): GameServicePackage {
            $prices = Arr::pull($payload, 'prices', []);
            $package = GameServicePackage::query()->create($payload);
            $this->syncPrices($package, $prices);
            $this->audit($admin, 'created', $package, [], $package->getAttributes(), $request);

            return $this->loadPackage($package);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updatePackage(GameServicePackage $package, array $payload, User $admin, Request $request): GameServicePackage
    {
        return DB::transaction(function () use ($package, $payload, $admin, $request): GameServicePackage {
            $locked = GameServicePackage::query()->lockForUpdate()->findOrFail($package->id);
            $old = $locked->getAttributes();
            $prices = Arr::pull($payload, 'prices', []);
            $locked->fill($payload)->save();
            $this->syncPrices($locked, $prices);
            $this->audit($admin, 'updated', $locked, $old, $locked->fresh()->getAttributes(), $request);

            return $this->loadPackage($locked);
        }, 3);
    }

    public function deletePackage(GameServicePackage $package, User $admin, Request $request): void
    {
        DB::transaction(function () use ($package, $admin, $request): void {
            $locked = GameServicePackage::query()->lockForUpdate()->findOrFail($package->id);

            if ($locked->orders()->exists()) {
                throw ValidationException::withMessages(['package' => 'Gói dịch vụ đã có đơn hàng. Hãy chuyển sang Tạm tắt để giữ lịch sử.']);
            }

            $old = $locked->getAttributes();
            $this->audit($admin, 'deleted', $locked, $old, [], $request);
            $locked->delete();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function orders(array $filters): LengthAwarePaginator
    {
        return $this->orderQuery($filters)
            ->with('collaborator:id,username,full_name')
            ->latest()
            ->paginate($this->perPage($filters));
    }

    public function reviewOrderCount(): int
    {
        return GameServiceOrder::query()->where('status', 'review')->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function orderSettlement(array $filters): array
    {
        $summary = $this->orderQuery($filters)
            ->where('status', 'completed')
            ->toBase()
            ->selectRaw('COUNT(*) as approved_orders')
            ->selectRaw('COUNT(CASE WHEN collaborator_total_cost IS NOT NULL AND net_profit IS NOT NULL THEN 1 END) as settled_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN collaborator_total_cost IS NOT NULL AND net_profit IS NOT NULL THEN total_amount ELSE 0 END), 0) as revenue')
            ->selectRaw('COALESCE(SUM(collaborator_total_cost), 0) as collaborator_cost')
            ->selectRaw('COALESCE(SUM(gross_profit), 0) as gross_profit')
            ->selectRaw('COALESCE(SUM(estimated_tax), 0) as estimated_tax')
            ->selectRaw('COALESCE(SUM(net_profit), 0) as net_profit')
            ->selectRaw('COUNT(CASE WHEN net_profit < 0 THEN 1 END) as loss_orders')
            ->selectRaw('COUNT(CASE WHEN collaborator_total_cost IS NULL OR net_profit IS NULL THEN 1 END) as legacy_orders')
            ->first();

        return [
            'approved_orders' => (int) ($summary?->approved_orders ?? 0),
            'settled_orders' => (int) ($summary?->settled_orders ?? 0),
            'revenue' => (int) ($summary?->revenue ?? 0),
            'collaborator_cost' => (int) ($summary?->collaborator_cost ?? 0),
            'gross_profit' => (int) ($summary?->gross_profit ?? 0),
            'estimated_tax' => (int) ($summary?->estimated_tax ?? 0),
            'net_profit' => (int) ($summary?->net_profit ?? 0),
            'loss_orders' => (int) ($summary?->loss_orders ?? 0),
            'legacy_orders' => (int) ($summary?->legacy_orders ?? 0),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function orderQuery(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return GameServiceOrder::query()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('code', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('service_name', 'like', "%{$search}%")
                ->orWhere('package_name', 'like', "%{$search}%")))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['game_service_id'] ?? null), fn (Builder $query) => $query->where('game_service_id', $filters['game_service_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['exclude_status'] ?? null), fn (Builder $query) => $query->where('status', '!=', $filters['exclude_status']));
    }

    /** @param array<string, mixed> $payload */
    public function updateOrder(GameServiceOrder $order, array $payload, User $admin, Request $request): GameServiceOrder
    {
        return DB::transaction(function () use ($order, $payload, $admin, $request): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $old = Arr::only($locked->getAttributes(), ['status', 'collaborator_id', 'admin_note', 'processing_at', 'completed_at']);
            $status = (string) $payload['status'];

            if ($status !== $locked->status && in_array($status, ['review', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái chờ duyệt và hoàn thành phải được cập nhật qua đúng quy trình duyệt báo cáo.',
                ]);
            }

            if ($status !== $locked->status && in_array($status, ['failed', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Hãy dùng thao tác hoàn tiền riêng để hủy hoặc đánh dấu đơn thất bại.',
                ]);
            }

            if ($locked->status === 'review' && ! in_array($status, ['review', 'processing'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Đơn chưa đạt yêu cầu chỉ có thể trả lại trạng thái đang xử lý để cộng tác viên tiếp tục thực hiện.',
                ]);
            }

            if ($locked->collaborator_held_at !== null
                && array_key_exists('collaborator_id', $payload)
                && (int) $payload['collaborator_id'] !== (int) $locked->collaborator_id) {
                throw ValidationException::withMessages(['collaborator_id' => 'Không thể đổi CTV sau khi đơn đã được kết toán.']);
            }

            if ($locked->status === 'review'
                && array_key_exists('collaborator_id', $payload)
                && (int) $payload['collaborator_id'] !== (int) $locked->collaborator_id) {
                throw ValidationException::withMessages([
                    'collaborator_id' => 'Không thể đổi cộng tác viên khi đơn đang chờ duyệt hoàn thành.',
                ]);
            }

            if (array_key_exists('collaborator_id', $payload)
                && $payload['collaborator_id'] !== null
                && (int) $payload['collaborator_id'] !== (int) $locked->collaborator_id
                && $locked->game_service_id !== null
                && ! User::query()
                    ->whereKey((int) $payload['collaborator_id'])
                    ->where(function (Builder $candidateQuery) use ($locked): void {
                        $candidateQuery
                            ->where('role', User::ROLE_ADMIN)
                            ->orWhereHas('allowedGameServices', fn (Builder $query) => $query->whereKey($locked->game_service_id));
                    })
                    ->exists()) {
                throw ValidationException::withMessages([
                    'collaborator_id' => 'CTV chưa được cấp quyền nhận dịch vụ của đơn này.',
                ]);
            }

            $payload['processing_at'] = $status === 'processing' ? ($locked->processing_at ?? now()) : $locked->processing_at;
            $payload['completed_at'] = $status === 'completed' ? ($locked->completed_at ?? now()) : null;
            $locked->fill($payload)->save();

            if ($status === 'processing' && $locked->collaborator_id !== null) {
                $this->affiliateWalletService->holdGameServiceOrder($locked);
            }
            $this->audit($admin, 'game_service_order_updated', $locked, $old, Arr::only($locked->fresh()->getAttributes(), array_keys($old)), $request);

            return $locked->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function approveOrderCompletion(GameServiceOrder $order, array $payload, User $admin, Request $request): GameServiceOrder
    {
        return DB::transaction(function () use ($order, $payload, $admin, $request): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'review') {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ có thể xác nhận đơn đang chờ duyệt hoàn thành.',
                ]);
            }

            $completionReport = $locked->progressUpdates()
                ->where('type', GameServiceOrderProgress::TYPE_COMPLETION)
                ->latest('id')
                ->first();

            if (! $completionReport instanceof GameServiceOrderProgress
                || blank($completionReport->image_path)
                || ! Storage::disk('local')->exists($completionReport->image_path)
                || $locked->collaborator_id === null
                || $completionReport->user_id !== $locked->collaborator_id) {
                throw ValidationException::withMessages([
                    'completion_report' => 'Báo cáo hoàn thành hoặc ảnh xác minh của cộng tác viên chưa hợp lệ.',
                ]);
            }

            $old = Arr::only($locked->getAttributes(), ['status', 'admin_note', 'completed_at']);
            $locked->forceFill([
                'status' => 'completed',
                'admin_note' => array_key_exists('admin_note', $payload) ? $payload['admin_note'] : $locked->admin_note,
                'completed_at' => $locked->completed_at ?? now(),
            ])->save();
            $this->affiliateWalletService->scheduleGameServiceOrderSettlement($locked);
            $this->audit(
                $admin,
                'game_service_order_completion_approved',
                $locked,
                $old,
                Arr::only($locked->fresh()->getAttributes(), array_keys($old)),
                $request,
            );

            return $locked->refresh()->load('collaborator:id,username,full_name');
        }, 3);
    }

    /** @param array{status: string, admin_note: string} $payload */
    public function refundOrder(GameServiceOrder $order, array $payload, User $admin, Request $request): GameServiceOrder
    {
        return DB::transaction(function () use ($order, $payload, $admin, $request): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->collaborator_refunded_at !== null) {
                throw ValidationException::withMessages(['order' => 'Đơn này đã được hoàn tiền trước đó.']);
            }

            if (! in_array($locked->status, ['pending', 'processing', 'review', 'completed'], true)) {
                throw ValidationException::withMessages(['order' => 'Trạng thái hiện tại không thể hoàn tiền.']);
            }

            $old = Arr::only($locked->getAttributes(), ['status', 'admin_note', 'collaborator_available_at', 'collaborator_refunded_at']);
            $refundedAmount = 0;

            if ($locked->user_id !== null) {
                $customer = User::query()->withoutGlobalScopes()->find($locked->user_id);
                if ($customer instanceof User) {
                    $refundedAmount = $this->gameServiceOrderService->refundWalletPayment($locked, $customer);
                }
            }

            $this->affiliateWalletService->refundGameServiceOrder($locked);
            $locked->forceFill([
                'status' => $payload['status'],
                'admin_note' => $payload['admin_note'],
                'collaborator_available_at' => null,
            ])->save();
            $this->audit(
                $admin,
                'game_service_order_refunded',
                $locked,
                $old,
                [
                    ...Arr::only($locked->fresh()->getAttributes(), array_keys($old)),
                    'customer_refunded_amount' => $refundedAmount,
                ],
                $request,
            );

            return $locked->refresh()->load('collaborator:id,username,full_name');
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $prices */
    private function syncPrices(GameServicePackage $package, array $prices): void
    {
        $codes = collect($prices)->pluck('code')->all();
        $pricesToDelete = $package->prices()->whereNotIn('code', $codes)->get();

        if ($pricesToDelete->contains(fn (GameServicePackagePrice $price): bool => $price->orders()->exists())) {
            throw ValidationException::withMessages(['prices' => 'Không thể xóa mức giá đã phát sinh đơn hàng.']);
        }

        $package->prices()->whereNotIn('code', $codes)->delete();

        foreach ($prices as $price) {
            $package->prices()->updateOrCreate(['code' => $price['code']], $price);
        }
    }

    private function loadService(GameService $service): GameService
    {
        return $service->refresh()->load(['game:id,name,slug,provider_service_code', 'servers:id,game_id,name,code'])->loadCount(['packages', 'orders']);
    }

    private function loadPackage(GameServicePackage $package): GameServicePackage
    {
        return $package->refresh()->load(['service:id,game_id,name', 'service.game:id,name', 'prices'])->loadCount('orders');
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(User $admin, string $action, Model $subject, array $old, array $new, Request $request): void
    {
        AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return min(max((int) ($filters['per_page'] ?? 20), 1), 100);
    }
}
