<?php

namespace App\Features\Topup\Services;

use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use Illuminate\Validation\ValidationException;

class TopupPackagePriceSourceService
{
    public function __construct(private readonly GlobalTopupPackageSyncService $syncService) {}

    /** @return array<string, mixed> */
    public function resolve(TopupPackage $package): array
    {
        $source = $this->resolveOrNull($package);

        if ($source === null) {
            throw ValidationException::withMessages([
                'package_id' => 'Gói nạp chưa được đồng bộ đúng từ gói Global hoặc gói Global đang tạm tắt.',
            ]);
        }

        return $source;
    }

    /** @return array<string, mixed>|null */
    public function resolveOrNull(TopupPackage $package): ?array
    {
        $package->loadMissing(['game', 'globalTopupPackage.provider', 'globalTopupPackage.gameSettings']);
        $game = $package->game;

        if (! $game instanceof Game || $game->package_mode !== 'global') {
            $retailPrice = (int) $package->price;

            return [
                'package_source' => 'custom',
                'retail_price' => $retailPrice,
                'original_price' => max($retailPrice, (int) ($package->original_price ?? $retailPrice)),
                'provider_price' => $package->provider_price === null ? null : (int) $package->provider_price,
                'global_topup_package_id' => null,
                'global_topup_package_name' => null,
            ];
        }

        $globalPackage = $package->globalTopupPackage;

        if (! $globalPackage instanceof GlobalTopupPackage
            || $globalPackage->status !== 'active'
            || $globalPackage->provider_price > $globalPackage->price) {
            return null;
        }

        $effectiveAttributes = $this->syncService->packageAttributes($globalPackage, $game, $package);

        if ($effectiveAttributes['status'] !== 'active') {
            return null;
        }

        $package->forceFill($effectiveAttributes);
        $package->setRelation('provider', $globalPackage->provider);

        return [
            'package_source' => 'global',
            'retail_price' => $globalPackage->price,
            'original_price' => max($globalPackage->price, $globalPackage->denomination),
            'provider_price' => $globalPackage->provider_price,
            'global_topup_package_id' => $globalPackage->id,
            'global_topup_package_name' => $globalPackage->name,
        ];
    }
}
