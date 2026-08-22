<?php

namespace App\Features\Topup\Events;

use App\Features\Topup\Support\OrderRealtimeChannel;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $channelName;

    public readonly string $paymentStatus;

    public readonly string $orderStatus;

    public readonly ?string $paidAt;

    public readonly ?string $processingAt;

    public readonly ?string $completedAt;

    public readonly ?string $failedAt;

    public readonly ?string $cancelledAt;

    public readonly string $updatedAt;

    /** @var array{total:int,pending:int,processing:int,completed:int,failed:int,cancelled:int} */
    public readonly array $recipientSummary;

    public function __construct(Order $order)
    {
        $this->channelName = OrderRealtimeChannel::for($order);
        $this->paymentStatus = $order->payment_status->value;
        $this->orderStatus = $order->order_status->value;
        $this->paidAt = $order->paid_at?->toISOString();
        $this->processingAt = $order->processing_at?->toISOString();
        $this->completedAt = $order->completed_at?->toISOString();
        $this->failedAt = $order->failed_at?->toISOString();
        $this->cancelledAt = $order->cancelled_at?->toISOString();
        $this->updatedAt = $order->updated_at?->toISOString() ?? now()->toISOString();
        $counts = $order->recipients()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $this->recipientSummary = [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'processing' => (int) ($counts['processing'] ?? 0),
            'completed' => (int) ($counts['completed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'cancelled' => (int) ($counts['cancelled'] ?? 0),
        ];
    }

    public function broadcastOn(): Channel
    {
        return new Channel($this->channelName);
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    /** @return array<string, string|array<string, int>|null> */
    public function broadcastWith(): array
    {
        return [
            'payment_status' => $this->paymentStatus,
            'order_status' => $this->orderStatus,
            'paid_at' => $this->paidAt,
            'processing_at' => $this->processingAt,
            'completed_at' => $this->completedAt,
            'failed_at' => $this->failedAt,
            'cancelled_at' => $this->cancelledAt,
            'updated_at' => $this->updatedAt,
            'recipient_summary' => $this->recipientSummary,
        ];
    }
}
