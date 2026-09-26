<?php

namespace App\Features\Topup\Services;

use App\Enums\OrderStatus;
use App\Features\Affiliate\Services\AffiliateCommissionService;
use App\Models\Order;
use DomainException;

class OrderStatusService
{
    public function __construct(private readonly AffiliateCommissionService $affiliateCommissionService) {}

    /** @var array<string, array<int, string>> */
    private array $transitions = [
        'pending' => ['processing', 'failed', 'cancelled'],
        'processing' => ['completed', 'failed', 'cancelled'],
        'failed' => ['processing', 'completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function transition(Order $order, OrderStatus $target, ?string $reason = null): Order
    {
        $current = $order->order_status->value;

        if ($current === $target->value) {
            return $order->refresh();
        }

        if (! in_array($target->value, $this->transitions[$current] ?? [], true)) {
            throw new DomainException("Không thể chuyển trạng thái đơn từ {$current} sang {$target->value}.");
        }

        $timestamps = match ($target) {
            OrderStatus::Processing => ['processing_at' => $order->processing_at ?? now(), 'failure_reason' => null],
            OrderStatus::Completed => [
                'completed_at' => now(),
                'failure_reason' => null,
            ],
            OrderStatus::Failed => ['failed_at' => now(), 'failure_reason' => $reason],
            OrderStatus::Cancelled => ['cancelled_at' => now(), 'failure_reason' => $reason],
            default => [],
        };

        $order->forceFill(['order_status' => $target, ...$timestamps])->save();

        match ($target) {
            OrderStatus::Processing => $order->recipients()
                ->whereIn('status', ['pending', 'failed'])
                ->update(['status' => 'processing', 'failure_reason' => null]),
            OrderStatus::Completed => $order->recipients()
                ->where('status', '!=', 'completed')
                ->update(['status' => 'completed', 'failure_reason' => null]),
            OrderStatus::Failed => $order->recipients()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->update(['status' => 'failed', 'failure_reason' => $reason]),
            OrderStatus::Cancelled => $order->recipients()
                ->where('status', '!=', 'completed')
                ->update(['status' => 'cancelled', 'failure_reason' => $reason]),
            default => null,
        };

        if ($target === OrderStatus::Completed) {
            $this->affiliateCommissionService->markOrderCompleted($order->refresh());
        }

        if ($target === OrderStatus::Cancelled) {
            $this->affiliateCommissionService->reverseForOrder($order, 'Đơn hàng đã bị hủy.');
        }

        return $order->refresh();
    }
}
