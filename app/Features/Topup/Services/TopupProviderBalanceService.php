<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\Contracts\TopupProviderBalanceInterface;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Models\Order;
use App\Models\TopupProvider;
use Illuminate\Validation\ValidationException;

class TopupProviderBalanceService
{
    public function __construct(private readonly TopupProviderResolver $providerResolver) {}

    public function forOrder(Order $order): TopupProviderBalanceDto
    {
        $order->loadMissing('provider');
        $provider = $order->provider;
        $adapter = $this->providerResolver->resolveForOrder($order);

        if (! $provider instanceof TopupProvider || ! $adapter instanceof TopupProviderBalanceInterface) {
            throw ValidationException::withMessages([
                'reorder' => 'Provider của đơn này không hỗ trợ kiểm tra số dư để reorder an toàn.',
            ]);
        }

        return $adapter->balance($provider);
    }
}
