<?php

namespace App\Features\Topup\Services;

use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use Illuminate\Validation\ValidationException;

class TopupPackagePriceSourceService
{
    /** @return array<string, mixed> */
    public function resolve(TopupPackage $package): array
    {
        $source = $this->resolveOrNull($package);

        if ($source === null) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói nạp chưa được ánh xạ đúng với gói Global hoặc gói Global đang tạm tắt.',
            ]);
        }

        return $source;
    }

    /** @return array<string, mixed>|null */
    public function resolveOrNull(TopupPackage $package): ?array
    {
        $package->loadMissing(['game', 'globalTopupPackage']);
        $game = $package->game;

        if (! $game instanceof Game || $game->package_mode !== 'global') {
            $retailPrice = (int) $package->price;

            return [
                'package_source' => 'custom',
                'retail_price' => $retailPrice,
                'original_price' => max($retailPrice, (int) ($package->original_price ?? $retailPrice)),
                'global_topup_package_id' => null,
                'global_topup_package_name' => null,
            ];
        }

        $globalPackage = $package->globalTopupPackage;

        if (! $globalPackage instanceof GlobalTopupPackage
            || $globalPackage->status !== 'active'
            || $globalPackage->denomination !== $package->denomination
            || ($package->provider_price !== null && (int) $package->provider_price > $globalPackage->price)) {
            return null;
        }

        return [
            'package_source' => 'global',
            'retail_price' => $globalPackage->price,
            'original_price' => max($globalPackage->price, $globalPackage->original_price),
            'global_topup_package_id' => $globalPackage->id,
            'global_topup_package_name' => $globalPackage->name,
        ];
    }
}
