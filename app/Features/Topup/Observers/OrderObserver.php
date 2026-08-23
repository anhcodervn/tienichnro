<?php

namespace App\Features\Topup\Observers;

use App\Enums\PaymentStatus;
use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Features\Topup\Events\AdminTopupOrderUpdated;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Models\Order;

class OrderObserver
{
    public function __construct(private readonly TopupDiscordReporterService $discordReporter) {}

    public function created(Order $order): void
    {
        $this->discordReporter->orderCreated($order);
        AdminTopupOrderUpdated::dispatch($order);
    }

    public function updated(Order $order): void
    {
        if ($order->wasChanged('payment_status')) {
            match ($order->payment_status) {
                PaymentStatus::Paid => $this->discordReporter->paymentReceived($order),
                PaymentStatus::Refunded => $this->discordReporter->refundIssued($order),
                default => null,
            };
        }

        if ($order->wasChanged('order_status')) {
            $this->discordReporter->orderStatusChanged($order);
        }

        $publicStatusChanged = $order->wasChanged([
            'payment_status',
            'order_status',
            'paid_at',
            'processing_at',
            'completed_at',
            'failed_at',
            'cancelled_at',
        ]);
        $adminVisibleStateChanged = $order->wasChanged([
            'payment_status',
            'order_status',
            'provider_reference',
            'failure_reason',
            'paid_at',
            'processing_at',
            'completed_at',
            'failed_at',
            'cancelled_at',
        ]);

        if (! $publicStatusChanged && ! $adminVisibleStateChanged) {
            return;
        }

        if ($publicStatusChanged) {
            OrderStatusUpdated::dispatch($order);
        }

        if ($adminVisibleStateChanged) {
            AdminTopupOrderUpdated::dispatch($order);
        }
    }
}
