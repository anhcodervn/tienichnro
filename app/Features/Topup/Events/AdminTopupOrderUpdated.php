<?php

namespace App\Features\Topup\Events;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminTopupOrderUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $orderId;

    public readonly string $code;

    public readonly string $paymentStatus;

    public readonly string $orderStatus;

    public readonly bool $canReorder;

    public readonly ?string $providerReference;

    public readonly ?string $failureReason;

    public readonly ?string $paidAt;

    public readonly string $updatedAt;

    public function __construct(Order $order)
    {
        $order->loadMissing('provider:id,slug');

        $this->orderId = $order->id;
        $this->code = $order->code;
        $this->paymentStatus = $order->payment_status->value;
        $this->orderStatus = $order->order_status->value;
        $this->canReorder = $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Failed
            && $order->provider?->slug === 'the9p';
        $this->providerReference = $order->provider_reference;
        $this->failureReason = $order->failure_reason;
        $this->paidAt = $order->paid_at?->toISOString();
        $this->updatedAt = $order->updated_at?->toISOString() ?? now()->toISOString();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.topup.orders');
    }

    public function broadcastAs(): string
    {
        return 'admin.topup.order.updated';
    }

    /** @return array<string, int|string|bool|null> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'code' => $this->code,
            'payment_status' => $this->paymentStatus,
            'order_status' => $this->orderStatus,
            'can_reorder' => $this->canReorder,
            'provider_reference' => $this->providerReference,
            'failure_reason' => $this->failureReason,
            'paid_at' => $this->paidAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
