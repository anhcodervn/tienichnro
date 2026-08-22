<?php

namespace App\Features\Topup\Contracts;

use App\Features\Topup\DTOs\TopupProviderBalanceDto;
use App\Models\TopupProvider;

interface TopupProviderBalanceInterface
{
    public function balance(TopupProvider $provider): TopupProviderBalanceDto;
}
