<?php

namespace App\Features\Admin\Topup\Services;

use App\Enums\TopupProviderType;
use App\Models\Game;
use App\Models\TopupProvider;
use Illuminate\Support\Str;

final class ProviderPayloadMappingTemplateService
{
    /** @return array{default:array<string, string>,services:array<string, array<string, string>>} */
    public function editorMapping(TopupProvider $provider): array
    {
        $saved = is_array($provider->payload_field_mapping) ? $provider->payload_field_mapping : [];
        $savedDefault = is_array($saved['default'] ?? null) ? $saved['default'] : [];
        $savedServices = is_array($saved['services'] ?? null) ? $saved['services'] : [];

        return [
            'default' => $savedDefault,
            'services' => $savedServices + $this->generatedServices($provider->type),
        ];
    }

    /** @return array<string, array<string, string>> */
    private function generatedServices(?TopupProviderType $providerType): array
    {
        if ($providerType === null || $providerType === TopupProviderType::Manual) {
            return [];
        }

        $services = [];

        Game::query()
            ->whereNotNull('provider_service_code')
            ->orderBy('id')
            ->get(['id', 'provider_service_code', 'checkout_fields'])
            ->each(function (Game $game) use (&$services, $providerType): void {
                $serviceCode = Str::lower(trim((string) $game->provider_service_code));

                if ($serviceCode === '' || array_key_exists($serviceCode, $services)) {
                    return;
                }

                $mapping = $this->mappingForGame($game, $providerType);

                if ($mapping !== []) {
                    $services[$serviceCode] = $mapping;
                }
            });

        return $services;
    }

    /** @return array<string, string> */
    private function mappingForGame(Game $game, TopupProviderType $providerType): array
    {
        $fields = collect($game->checkoutFields());
        $keys = $fields->pluck('key')->filter()->values();
        $primarySource = $keys->contains('game_account')
            ? 'game_account'
            : ($keys->contains('character_name')
                ? 'character_name'
                : (string) ($fields->firstWhere('required', true)['key'] ?? $keys->first() ?? ''));

        if ($primarySource === '') {
            return [];
        }

        [$primaryTarget, $characterTarget] = match ($providerType) {
            TopupProviderType::MerchantPartnerCard => ['username', 'charname'],
            TopupProviderType::AccNro => ['account', 'character_name'],
            TopupProviderType::Manual => ['', ''],
        };

        $mapping = [];
        foreach ($keys as $key) {
            $mapping[$key] = match (true) {
                $key === $primarySource => $primaryTarget,
                $key === 'character_name' => $characterTarget,
                default => $key,
            };
        }

        return $mapping;
    }
}
