<?php

namespace App\Features\Topup\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Jobs\ProcessTopupRecipient;
use App\Mail\Orders\OrderProcessingMail;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class TopupService
{
    public function __construct(
        private readonly OrderStatusService $statusService,
        private readonly ProviderBalanceFallbackService $providerBalanceFallbackService,
        private readonly TopupProviderResolver $providerResolver,
    ) {}

    public function process(int $orderId): void
    {
        $shouldSendProcessingMail = false;
        $order = DB::transaction(function () use ($orderId, &$shouldSendProcessingMail): ?Order {
            $order = Order::query()->lockForUpdate()->find($orderId);

            if (! $order instanceof Order || $order->payment_status !== PaymentStatus::Paid) {
                return null;
            }

            if (in_array($order->order_status, [OrderStatus::Completed, OrderStatus::Cancelled], true)) {
                return null;
            }

            $shouldSendProcessingMail = $order->order_status !== OrderStatus::Processing;

            return $this->statusService->transition($order, OrderStatus::Processing);
        }, 3);

        if (! $order instanceof Order) {
            return;
        }

        if (! $this->providerBalanceFallbackService->divertIfInsufficient($order->id)) {
            $supportsBatchQuantity = $this->providerResolver->resolveForOrder($order)->supportsBatchQuantity();

            $order->recipients()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderBy('position')
                ->get(['id', 'quantity', 'provider_response'])
                ->each(function ($recipient) use ($supportsBatchQuantity): void {
                    $items = data_get($recipient->provider_response, 'items', []);
                    $hasLegacyUnitItems = is_array($items)
                        && $items !== []
                        && (int) data_get($items, '1.quantity', 0) !== $recipient->quantity;

                    if ($supportsBatchQuantity && ! $hasLegacyUnitItems) {
                        ProcessTopupRecipient::dispatch($recipient->id)->afterCommit();

                        return;
                    }

                    foreach (range(1, $recipient->quantity) as $unit) {
                        ProcessTopupRecipient::dispatch($recipient->id, $unit)->afterCommit();
                    }
                });
        }

        if ($shouldSendProcessingMail) {
            Mail::to($order->email)->queue(new OrderProcessingMail($order->refresh()));
        }
    }
}
