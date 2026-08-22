<?php

namespace App\Features\Topup\Support;

use App\Models\Order;
use LogicException;

final class OrderRealtimeChannel
{
    public static function for(Order $order): string
    {
        $applicationKey = (string) config('app.key');

        if ($applicationKey === '') {
            throw new LogicException('APP_KEY is required to create an order realtime channel.');
        }

        $token = hash_hmac(
            'sha256',
            "order-status:{$order->getKey()}:{$order->code}",
            $applicationKey,
        );

        return "orders.{$token}";
    }
}
