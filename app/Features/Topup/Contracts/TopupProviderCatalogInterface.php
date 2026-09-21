<?php

namespace App\Features\Topup\Contracts;

use App\Features\Topup\DTOs\TopupProviderProductDto;
use App\Models\TopupProvider;
use Illuminate\Support\Collection;

interface TopupProviderCatalogInterface
{
    /** @return Collection<int, TopupProviderProductDto> */
    public function products(TopupProvider $provider): Collection;
}
