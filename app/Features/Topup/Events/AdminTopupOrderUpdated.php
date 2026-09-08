<?php

namespace App\Features\Topup\Events;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Services\TopupProviderResolver;
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

    public readonly ?string $topupId;

    public readonly string $paymentStatus;

    public readonly string $orderStatus;

    public readonly bool $canReorder;

    public readonly bool $canSyncProvider;

    public readonly bool $canRetryProviderSubmission;

    public readonly ?string $providerReference;

    public readonly ?string $failureReason;

    public readonly ?string $paidAt;

    public readonly string $updatedAt;

    public readonly int $tenantId;

    public function __construct(Order $order)
    {
        $order->loadMissing('provider:id,slug');

        $this->orderId = $order->id;
        $this->tenantId = (int) $order->tenant_id;
        $this->code = $order->code;
        $this->topupId = $order->topup_id;
        $this->paymentStatus = $order->payment_status->value;
        $this->orderStatus = $order->order_status->value;
        $this->canReorder = $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Failed
            && TopupProviderResolver::supportsBalance($order->provider?->slug);
        $this->canSyncProvider = $order->payment_status === PaymentStatus::Paid
            && in_array($order->order_status, [OrderStatus::Processing, OrderStatus::Completed], true)
            && TopupProviderResolver::supportsStatusChecks($order->provider?->slug);
        $this->canRetryProviderSubmission = $order->payment_status === PaymentStatus::Paid
            && $order->order_status === OrderStatus::Processing
            && TopupProviderResolver::supportsBalance($order->provider?->slug)
            && data_get($order->metadata, 'provider_manual_review.code') === 'provider_balance_insufficient';
        $this->providerReference = $order->provider_reference;
        $this->failureReason = $order->failure_reason;
        $this->paidAt = $order->paid_at?->toISOString();
        $this->updatedAt = $order->updated_at?->toISOString() ?? now()->toISOString();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("admin.sites.{$this->tenantId}.topup.orders"),
            new PrivateChannel('admin.platform.topup.orders'),
        ];
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
            'tenant_id' => $this->tenantId,
            'code' => $this->code,
            'topup_id' => $this->topupId,
            'payment_status' => $this->paymentStatus,
            'order_status' => $this->orderStatus,
            'can_reorder' => $this->canReorder,
            'can_sync_provider' => $this->canSyncProvider,
            'can_retry_provider_submission' => $this->canRetryProviderSubmission,
            'provider_reference' => $this->providerReference,
            'failure_reason' => $this->failureReason,
            'paid_at' => $this->paidAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
