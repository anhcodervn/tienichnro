<?php

namespace App\Features\Topup\DTOs;

final readonly class TopupProviderProductDto
{
    public function __construct(
        public string $serviceCode,
        public int $denomination,
        public int $price,
    ) {}
}
