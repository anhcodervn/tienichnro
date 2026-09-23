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

        foreach ($this->canonicalFields($fields) as $key => $value) {
            $mapped[$mapping[$key] ?? $key] = $value;
        }

        return $mapped;
    }

    public function outputKey(Order $order, TopupProvider $provider, string $sourceKey, string $fallback): string
    {
        return $this->resolvedMapping($order, $provider)[$sourceKey] ?? $fallback;
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{0:string,1:string,2:array<string, string>}
     */
    public function extractPrimary(Order $order, array $fields, string $legacyProviderKey): array
    {
        $fields = $this->canonicalFields($fields);
        $candidateKeys = ['game_account', 'character_name', $legacyProviderKey];

        foreach ($order->checkout_fields_snapshot ?? [] as $field) {
            $key = is_array($field) ? $this->canonicalKey((string) ($field['key'] ?? '')) : '';

            if ($key !== '') {
                $candidateKeys[] = $key;
            }
        }

        $candidateKeys = array_values(array_unique($candidateKeys));
        foreach ($candidateKeys as $key) {
            if (filled($fields[$key] ?? null)) {
                $value = $fields[$key];
                unset($fields[$key]);

                return [$value, $key, $fields];
            }
        }

        $key = (string) collect($fields)->search(fn (string $value): bool => $value !== '');
        $value = $key !== '' ? $fields[$key] : '';

        if ($key !== '') {
            unset($fields[$key]);
        }

        return [$value, $key, $fields];
    }

    /** @param array<string, string> $fields
     * @return array<string, string>
     */
    private function canonicalFields(array $fields): array
    {
        if (! array_key_exists('character_name', $fields) && array_key_exists('game_character', $fields)) {
            $fields['character_name'] = $fields['game_character'];
        }

        unset($fields['game_character']);

        return $fields;
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

        $mapping = [...$default, ...$service];

        if (! array_key_exists('character_name', $mapping) && array_key_exists('game_character', $mapping)) {
            $mapping['character_name'] = $mapping['game_character'];
        }

        return $mapping;
    }

    private function canonicalKey(string $key): string
    {
        return $key === 'game_character' ? 'character_name' : $key;
    }
}
