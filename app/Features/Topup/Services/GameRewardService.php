<?php

namespace App\Features\Topup\Services;

use App\Models\GlobalTopupPackageGameSetting;
use App\Models\TopupPackage;
use Illuminate\Support\Collection;

class GameRewardService
{
    /**
     * @param  Collection<int, TopupPackage>  $packages
     * @param  Collection<int, GlobalTopupPackageGameSetting>|null  $settings
     */
    public function applyToPackages(Collection $packages, ?Collection $settings = null): void
    {
        $packagesWithDenomination = $packages->filter(
            fn (TopupPackage $package): bool => $package->denomination !== null,
        );

        if ($packagesWithDenomination->isEmpty()) {
            return;
        }

        $settings ??= GlobalTopupPackageGameSetting::query()
            ->whereIn('game_id', $packagesWithDenomination->pluck('game_id')->unique())
            ->whereIn('denomination', $packagesWithDenomination->pluck('denomination')->unique())
            ->get();
        $settings = $settings->keyBy(
            fn (GlobalTopupPackageGameSetting $setting): string => $this->key($setting->game_id, $setting->denomination),
        );

        $packagesWithDenomination->each(function (TopupPackage $package) use ($settings): void {
            $setting = $settings->get($this->key($package->game_id, $package->denomination));

            if ($setting instanceof GlobalTopupPackageGameSetting) {
                $this->applySetting($package, $setting);
            }
        });
    }

    public function applyToPackage(TopupPackage $package): void
    {
        if ($package->denomination === null) {
            return;
        }

        $setting = GlobalTopupPackageGameSetting::query()
            ->where('game_id', $package->game_id)
            ->where('denomination', $package->denomination)
            ->first();

        if ($setting instanceof GlobalTopupPackageGameSetting) {
            $this->applySetting($package, $setting);
        }
    }

    public function applySetting(TopupPackage $package, GlobalTopupPackageGameSetting $setting): void
    {
        $receives = is_array($setting->receives) ? array_values($setting->receives) : [];
        $primaryReceive = $receives[0] ?? [];

        $package->forceFill([
            'carot_amount' => data_get($primaryReceive, 'base_amount'),
            'reward_x2_amount' => data_get($primaryReceive, 'reward_x2_amount'),
            'reward_x3_amount' => data_get($primaryReceive, 'reward_x3_amount'),
            'first_topup_reward_amount' => data_get($primaryReceive, 'first_topup_reward_amount'),
            'metadata' => [
                ...($package->metadata ?? []),
                'global_receives' => $receives,
            ],
        ]);
    }

    private function key(int $gameId, int $denomination): string
    {
        return $gameId.':'.$denomination;
    }
}
