<?php

namespace App\Features\Topup\Services;

use App\Models\Order;
use App\Models\TopupProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class TopupProviderPayloadFieldMapperService
{
    /**
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    public function map(Order $order, TopupProvider $provider, array $fields): array
    {
        $mapping = $this->resolvedMapping($order, $provider);
        $mapped = [];

        foreach ($fields as $key => $value) {
            $mapped[$mapping[$key] ?? $key] = $value;
        }

        return $mapped;
    }

    public function outputKey(Order $order, TopupProvider $provider, string $sourceKey, string $fallback): string
    {
        return $this->resolvedMapping($order, $provider)[$sourceKey] ?? $fallback;
    }

    /** @return array<string, string> */
    private function resolvedMapping(Order $order, TopupProvider $provider): array
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $snapshotPath = 'provider.payload_field_mapping';
        $configuration = Arr::has($metadata, $snapshotPath)
            ? data_get($metadata, $snapshotPath)
            : $provider->payload_field_mapping;

        if (! is_array($configuration)) {
            return [];
        }

        $default = is_array($configuration['default'] ?? null) ? $configuration['default'] : [];
        $serviceCode = Str::lower(trim((string) data_get($metadata, 'provider.service_code')));
        $services = is_array($configuration['services'] ?? null) ? $configuration['services'] : [];
        $service = is_array($services[$serviceCode] ?? null) ? $services[$serviceCode] : [];

        return [...$default, ...$service];
    }
}
