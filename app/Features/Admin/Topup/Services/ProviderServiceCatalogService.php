<?php

namespace App\Features\Admin\Topup\Services;

use App\Features\Topup\Contracts\TopupProviderCatalogInterface;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\TopupProvider;

final class ProviderServiceCatalogService
{
    public function __construct(private readonly TopupProviderResolver $providerResolver) {}

    /**
     * @return array{
     *     provider: array{id:int,name:string,slug:string,type:string|null},
     *     fetched_at:string,
     *     response:array<string,mixed>
     * }
     */
    public function fetch(TopupProvider $provider): array
    {
        $adapter = $this->providerResolver->resolve($provider);

        if (! $adapter instanceof TopupProviderCatalogInterface) {
            throw new TopupProviderConnectionException(
                'catalog_unsupported',
                'Provider này không hỗ trợ lấy danh sách services tự động.',
            );
        }

        return [
            'provider' => [
                'id' => $provider->id,
                'name' => $provider->name,
                'slug' => $provider->slug,
                'type' => $provider->type?->value,
            ],
            'fetched_at' => now()->toISOString(),
            'response' => $this->redactSensitiveValues($adapter->catalogResponse($provider)),
        ];
    }

    private function redactSensitiveValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/(?:secret|token|password|passphrase|api[_-]?key|partner[_-]?key|authorization|private[_-]?key|proxy|sign(?:ature)?)/i', $key) === 1) {
                $value[$key] = '[REDACTED]';

                continue;
            }

            $value[$key] = $this->redactSensitiveValues($item);
        }

        return $value;
    }
}
