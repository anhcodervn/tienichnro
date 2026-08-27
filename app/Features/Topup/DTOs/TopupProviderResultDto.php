<?php

namespace App\Features\Topup\DTOs;

use App\Features\Topup\Enums\TopupProviderStatus;

final readonly class TopupProviderResultDto
{
    /**
     * @param  array<string, scalar|null>  $response
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $providerResponse
     */
    public function __construct(
        public TopupProviderStatus $status,
        public ?string $reference = null,
        public ?string $message = null,
        public array $response = [],
        public array $request = [],
        public array $providerResponse = [],
    ) {}
}
