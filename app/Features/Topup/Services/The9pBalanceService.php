<?php

namespace App\Features\Topup\Services;

use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Features\Topup\Exceptions\The9pBalanceUnavailableException;
use App\Features\Topup\Providers\The9pTopupProvider;
use App\Models\TopupProvider;
use Throwable;

class The9pBalanceService
{
    public function __construct(private readonly The9pTopupProvider $the9pProvider) {}

    public function check(): TopupProviderBalanceDto
    {
        try {
            $provider = TopupProvider::query()
                ->where('slug', 'the9p')
                ->firstOrFail();

            return $this->the9pProvider->balance($provider);
        } catch (Throwable $exception) {
            throw new The9pBalanceUnavailableException(previous: $exception);
        }
    }
}
