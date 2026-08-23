<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\Contracts\TopupProviderBalanceInterface;
use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Models\Order;
use App\Models\TopupProvider;
use Illuminate\Validation\ValidationException;
use Throwable;

class TopupProviderBalanceService
{
    public function __construct(private readonly TopupProviderResolver $providerResolver) {}

    public function forOrder(Order $order): TopupProviderBalanceDto
    {
        $order->loadMissing('provider');
        $provider = $order->provider;
        if (! $provider instanceof TopupProvider) {
            throw ValidationException::withMessages([
                'reorder' => 'Provider của đơn này không hỗ trợ kiểm tra số dư để reorder an toàn.',
            ]);
        }

        return $this->forProvider($provider);
    }

    public function forProvider(TopupProvider $provider): TopupProviderBalanceDto
    {
        $adapter = $this->providerResolver->resolve($provider);

        if (! $adapter instanceof TopupProviderBalanceInterface) {
            throw ValidationException::withMessages(['provider' => 'Provider này không hỗ trợ kiểm tra số dư.']);
        }

        try {
            $balance = $adapter->balance($provider);
            $provider->forceFill([
                'balance' => $balance->balance,
                'balance_currency' => $balance->currency,
                'balance_status' => 'success',
                'balance_checked_at' => now(),
                'balance_error_code' => null,
                'balance_error_message' => null,
            ])->save();

            return $balance;
        } catch (Throwable $exception) {
            $diagnostic = TopupProviderConnectionException::fromThrowable($exception);
            $provider->forceFill([
                'balance_status' => 'failed',
                'balance_checked_at' => now(),
                'balance_error_code' => $diagnostic->errorCode,
                'balance_error_message' => mb_substr($diagnostic->getMessage(), 0, 500),
            ])->save();

            throw $diagnostic;
        }
    }
}
