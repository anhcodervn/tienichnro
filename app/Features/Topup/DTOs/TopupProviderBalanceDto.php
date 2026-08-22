<?php

namespace App\Features\Topup\DTOs;

final readonly class TopupProviderBalanceDto
{
    public function __construct(
        public int $balance,
        public string $currency,
        public int $warningThreshold = 0,
        public bool $isBelowWarningThreshold = false,
    ) {}
}
