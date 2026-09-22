<?php

namespace App\Features\Topup\Services;

use App\Enums\TopupProviderType;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;

class GlobalTopupPackageSyncService
{
    public function sync(GlobalTopupPackage $globalPackage): void
    {
        $globalPackage->loadMissing(['provider:id,slug,type', 'gameSettings']);

        Game::query()
            ->where('package_mode', 'global')
            ->orderBy('id')
            ->each(fn (Game $game) => $this->syncPackageToGame($globalPackage, $game));
    }

    public function syncGame(Game $game): void
    {
        if ($game->package_mode !== 'global') {
            $game->packages()->whereNotNull('global_topup_package_id')->update(['status' => 'inactive']);

            return;
        }

        $game->packages()->whereNull('global_topup_package_id')->update(['status' => 'inactive']);

        GlobalTopupPackage::query()
            ->with(['provider:id,slug,type', 'gameSettings'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->each(fn (GlobalTopupPackage $globalPackage) => $this->syncPackageToGame($globalPackage, $game));
    }

    public function syncPackageForGame(GlobalTopupPackage $globalPackage, Game $game): void
    {
        $globalPackage->loadMissing(['provider:id,slug,type', 'gameSettings']);
        $this->syncPackageToGame($globalPackage, $game);
    }

    /** @return array<string, mixed> */
    public function packageAttributes(GlobalTopupPackage $globalPackage, Game $game, ?TopupPackage $topupPackage = null): array
    {
        $setting = $globalPackage->gameSettings->firstWhere('game_id', $game->id);
        $serviceCode = trim((string) $game->provider_service_code);
        $receives = $this->receivesFor($globalPackage, $game);
        $primaryReceive = $receives[0] ?? [];
        $requiresServiceCode = $globalPackage->provider_id !== null
            && $globalPackage->provider?->type !== TopupProviderType::Manual;
        $isReady = (! $requiresServiceCode || $serviceCode !== '')
            && $setting !== null
            && $receives !== [];

        return [
            'provider_id' => $globalPackage->provider_id,
            'name' => $globalPackage->name,
            'denomination' => $globalPackage->denomination,
            'carot_amount' => data_get($primaryReceive, 'base_amount'),
            'reward_x2_amount' => data_get($primaryReceive, 'reward_x2_amount'),
            'reward_x3_amount' => data_get($primaryReceive, 'reward_x3_amount'),
            'first_topup_reward_amount' => data_get($primaryReceive, 'first_topup_reward_amount'),
            'provider_price' => $globalPackage->provider_price,
            'price' => $globalPackage->price,
            'original_price' => $globalPackage->denomination,
            'description' => $globalPackage->description,
            'bonus_text' => $globalPackage->bonus_text,
            'status' => $globalPackage->status === 'active' && $isReady ? 'active' : 'inactive',
            'sort_order' => $globalPackage->sort_order,
            'metadata' => [
                ...($globalPackage->metadata ?? []),
                'managed_by' => 'global_topup_package',
                'global_receives' => $receives,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function receivesFor(GlobalTopupPackage $globalPackage, Game $game): array
    {
        $setting = $globalPackage->gameSettings->firstWhere('game_id', $game->id);

        if ($setting !== null && is_array($setting->receives) && $setting->receives !== []) {
            return array_values($setting->receives);
        }

        return [];
    }

    private function syncPackageToGame(GlobalTopupPackage $globalPackage, Game $game): void
    {
        $package = TopupPackage::query()
            ->whereBelongsTo($game)
            ->whereBelongsTo($globalPackage, 'globalTopupPackage')
            ->whereNull('game_server_id')
            ->oldest('id')
            ->first();

        if (! $package instanceof TopupPackage) {
            $package = new TopupPackage([
                'game_id' => $game->id,
                'game_server_id' => null,
                'global_topup_package_id' => $globalPackage->id,
            ]);
        }

        $package->fill($this->packageAttributes($globalPackage, $game))->save();

        TopupPackage::query()
            ->whereBelongsTo($game)
            ->whereBelongsTo($globalPackage, 'globalTopupPackage')
            ->where('id', '!=', $package->getKey())
            ->update(['status' => 'inactive']);
    }
}
