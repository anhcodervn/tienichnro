<?php

namespace App\Features\Topup\DTOs;

use App\Features\Topup\Enums\TopupProviderStatus;

final readonly class TopupProviderResultDto
{
    /** @param array<string, scalar|null> $response */
    public function __construct(
        public TopupProviderStatus $status,
        public ?string $reference = null,
        public ?string $message = null,
        public array $response = [],
    ) {}
}
