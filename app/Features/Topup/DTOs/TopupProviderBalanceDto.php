<?php

namespace App\Features\Topup\DTOs;

final readonly class TopupProviderBalanceDto
{
    public function __construct(
        public int $balance,
        public string $currency,
    ) {}
}
