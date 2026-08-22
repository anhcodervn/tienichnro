<?php

namespace App\Features\Admin\Topup\Actions;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Enums\TopupProviderStatus;
use App\Features\Topup\Jobs\ProcessTopupRecipient;
use App\Features\Topup\Services\OrderStatusService;
use App\Features\Topup\Services\TopupProviderBalanceService;
use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReorderFailedTopupOrderAction
{
    public function __construct(
        private readonly TopupProviderBalanceService $providerBalanceService,
        private readonly OrderStatusService $orderStatusService,
    ) {}

    public function handle(Order $order, User $admin, Request $request): Order
    {
        $this->assertEligible($order->loadMissing(['provider', 'recipients']));

        try {
            $balanceBefore = $this->providerBalanceService->forOrder($order);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'reorder' => 'Không thể kiểm tra số dư provider lúc này. Chưa thực hiện reorder.',
            ]);
        }

        /** @var array{order: Order, failed_units: array<int, array<int, int>>} $result */
        $result = DB::transaction(function () use ($order, $admin, $request, $balanceBefore): array {
            $lockedOrder = Order::query()
                ->with('provider')
                ->lockForUpdate()
                ->findOrFail($order->id);
            $recipients = $lockedOrder->recipients()->lockForUpdate()->get();
            $lockedOrder->setRelation('recipients', $recipients);

            $failedUnits = $this->assertEligible($lockedOrder);
            $metadata = is_array($lockedOrder->metadata) ? $lockedOrder->metadata : [];
            $previousReorder = is_array($metadata['reorder'] ?? null) ? $metadata['reorder'] : null;
            $attempt = max((int) ($previousReorder['attempt'] ?? 0), 0) + 1;

            if ($previousReorder !== null) {
                $history = is_array($metadata['reorder_history'] ?? null) ? $metadata['reorder_history'] : [];
                $history[] = $previousReorder;
                $metadata['reorder_history'] = array_slice($history, -10);
            }

            $hasCompletedUnit = $recipients->contains(fn (OrderRecipient $recipient): bool => $recipient->status === 'completed');

            foreach ($recipients as $recipient) {
                $providerResponse = is_array($recipient->provider_response) ? $recipient->provider_response : [];
                $items = is_array($providerResponse['items'] ?? null) ? $providerResponse['items'] : [];

                foreach (range(1, $recipient->quantity) as $unit) {
                    $item = is_array($items[(string) $unit] ?? null) ? $items[(string) $unit] : [];

                    if (($item['status'] ?? null) === TopupProviderStatus::Completed->value) {
                        $hasCompletedUnit = true;
                    }
                }

                if ($recipient->status !== 'failed') {
                    continue;
                }

                $requestId = $this->requestId($lockedOrder, $recipient, $attempt);
                $reorderHistory = is_array($providerResponse['reorder_history'] ?? null)
                    ? $providerResponse['reorder_history']
                    : [];
                $reorderHistory[] = [
                    'attempt' => $attempt,
                    'reordered_at' => now()->toISOString(),
                    'items' => collect($items)
                        ->filter(fn (mixed $item): bool => is_array($item) && ($item['status'] ?? null) === TopupProviderStatus::Failed->value)
                        ->values()
                        ->all(),
                ];

                foreach ($failedUnits[$recipient->id] as $unit) {
                    $items[(string) $unit] = [
                        'unit' => $unit,
                        'request_id' => $recipient->quantity === 1
                            ? $requestId
                            : $requestId.'-U'.str_pad((string) $unit, 3, '0', STR_PAD_LEFT),
                        'status' => TopupProviderStatus::Pending->value,
                    ];
                }

                $providerResponse['items'] = $items;
                $providerResponse['reorder_history'] = array_slice($reorderHistory, -10);

                $recipient->forceFill([
                    'status' => 'processing',
                    'provider_request_id' => $requestId,
                    'provider_reference' => collect($items)->contains(
                        fn (mixed $item): bool => is_array($item)
                            && ($item['status'] ?? null) === TopupProviderStatus::Completed->value
                            && filled($item['reference'] ?? null),
                    ) ? $recipient->provider_reference : null,
                    'provider_status' => TopupProviderStatus::Processing->value,
                    'provider_response' => $providerResponse,
                    'status_check_attempts' => 0,
                    'failure_reason' => null,
                    'submitted_at' => null,
                    'last_checked_at' => null,
                    'completed_at' => null,
                    'failed_at' => null,
                ])->save();
            }

            $metadata['reorder'] = [
                'attempt' => $attempt,
                'requested_at' => now()->toISOString(),
                'requested_by' => $admin->id,
                'provider_id' => $lockedOrder->topup_provider_id,
                'balance_before' => $balanceBefore->balance,
                'currency' => $balanceBefore->currency,
                'status' => 'queued',
            ];

            $lockedOrder->forceFill([
                'metadata' => $metadata,
                'provider_reference' => $hasCompletedUnit ? $lockedOrder->provider_reference : null,
                'processing_at' => null,
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();
            $this->orderStatusService->transition($lockedOrder, OrderStatus::Processing);

            AdminAuditLog::query()->create([
                'admin_id' => $admin->id,
                'action' => 'order_reorder',
                'subject_type' => Order::class,
                'subject_id' => $lockedOrder->id,
                'old_values' => [
                    'order_status' => OrderStatus::Failed->value,
                    'reorder_attempt' => $attempt - 1,
                ],
                'new_values' => [
                    'order_status' => OrderStatus::Processing->value,
                    'reorder_attempt' => $attempt,
                    'failed_units' => collect($failedUnits)->flatten()->count(),
                    'provider_balance_before' => $balanceBefore->balance,
                    'provider_currency' => $balanceBefore->currency,
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return [
                'order' => $lockedOrder->refresh(),
                'failed_units' => $failedUnits,
            ];
        }, 3);

        foreach ($result['failed_units'] as $recipientId => $units) {
            foreach ($units as $unit) {
                ProcessTopupRecipient::dispatch($recipientId, $unit)->afterCommit();
            }
        }

        return $result['order'];
    }

    /** @return array<int, array<int, int>> */
    private function assertEligible(Order $order): array
    {
        if ($order->payment_status !== PaymentStatus::Paid || $order->order_status !== OrderStatus::Failed) {
            throw ValidationException::withMessages([
                'reorder' => 'Chỉ có thể reorder đơn đã thanh toán và đang ở trạng thái thất bại.',
            ]);
        }

        if ($order->topup_provider_id === null || blank(data_get($order->metadata, 'provider.slug', $order->provider?->slug))) {
            throw ValidationException::withMessages([
                'reorder' => 'Đơn này không có provider tự động để reorder.',
            ]);
        }

        $failedUnits = [];

        foreach ($order->recipients as $recipient) {
            if ($recipient->status !== 'failed') {
                continue;
            }

            $items = data_get($recipient->provider_response, 'items');

            if (! is_array($items)) {
                $this->throwAmbiguousState();
            }

            foreach (range(1, $recipient->quantity) as $unit) {
                $status = data_get($items, $unit.'.status');

                if (! in_array($status, [TopupProviderStatus::Completed->value, TopupProviderStatus::Failed->value], true)) {
                    $this->throwAmbiguousState();
                }

                if ($status === TopupProviderStatus::Failed->value) {
                    $failedUnits[$recipient->id][] = $unit;
                }
            }

            if (! isset($failedUnits[$recipient->id])) {
                $this->throwAmbiguousState();
            }
        }

        if ($failedUnits === []) {
            throw ValidationException::withMessages([
                'reorder' => 'Không tìm thấy lượt nạp thất bại nào đủ điều kiện reorder.',
            ]);
        }

        return $failedUnits;
    }

    private function throwAmbiguousState(): never
    {
        throw ValidationException::withMessages([
            'reorder' => 'Trạng thái provider chưa xác định rõ. Hãy đối soát thủ công để tránh nạp trùng.',
        ]);
    }

    private function requestId(Order $order, OrderRecipient $recipient, int $attempt): string
    {
        return $order->code
            .'-R'.str_pad((string) $recipient->position, 3, '0', STR_PAD_LEFT)
            .'-A'.str_pad((string) $attempt, 3, '0', STR_PAD_LEFT);
    }
}
