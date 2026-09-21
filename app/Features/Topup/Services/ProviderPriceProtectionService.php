<?php

namespace App\Features\Topup\Services;

use App\Features\Admin\Topup\Services\ProviderProductCatalogSyncService;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProviderPriceProtectionService
{
    public function __construct(
        private readonly ProviderProductCatalogSyncService $catalogSyncService,
        private readonly GlobalTopupPackageSyncService $globalPackageSyncService,
    ) {}

    /** @return array<string, mixed> */
    public function run(): array
    {
        $syncResults = $this->catalogSyncService->refresh();
        $successfulProviderIds = collect($syncResults)
            ->filter(fn (array $result): bool => ($result['status'] ?? null) === 'success')
            ->keys()
            ->map(fn (mixed $providerId): int => (int) $providerId)
            ->all();
        $adjustments = [];

        TopupProvider::query()
            ->whereKey($successfulProviderIds)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'connection_config'])
            ->each(function (TopupProvider $provider) use (&$adjustments): void {
                $adjustments[$provider->id] = $this->adjustProviderPrices($provider);
            });

        return [
            'sync_results' => $syncResults,
            'price_adjustments' => $adjustments,
            'providers_synced' => count($successfulProviderIds),
            'packages_checked' => collect($adjustments)->sum('checked'),
            'packages_adjusted' => collect($adjustments)->sum('adjusted'),
            'packages_capped' => collect($adjustments)->sum('capped'),
        ];
    }

    /** @return array{minimum_profit_percent: float, enabled: bool, checked: int, adjusted: int, capped: int} */
    private function adjustProviderPrices(TopupProvider $provider): array
    {
        $minimumProfitPercent = $this->minimumProfitPercent($provider);
        $summary = [
            'minimum_profit_percent' => $minimumProfitPercent,
            'enabled' => $minimumProfitPercent > 0,
            'checked' => 0,
            'adjusted' => 0,
            'capped' => 0,
        ];

        if ($minimumProfitPercent <= 0) {
            return $summary;
        }

        [$summary, $updatedGlobalPackageIds] = DB::transaction(function () use ($provider, $minimumProfitPercent, $summary): array {
            $globalPackageIds = [];
            $customPackages = TopupPackage::query()
                ->whereBelongsTo($provider, 'provider')
                ->whereNull('global_topup_package_id')
                ->active()
                ->lockForUpdate()
                ->get(['id', 'provider_price', 'price', 'original_price']);
            $globalPackages = GlobalTopupPackage::query()
                ->whereBelongsTo($provider, 'provider')
                ->where('status', 'active')
                ->lockForUpdate()
                ->get(['id', 'provider_price', 'price', 'original_price']);

            foreach ($customPackages as $package) {
                $summary = $this->adjustPackage($package, $minimumProfitPercent, $summary);
            }

            foreach ($globalPackages as $package) {
                $oldPrice = (int) $package->price;
                $summary = $this->adjustPackage($package, $minimumProfitPercent, $summary);

                if ((int) $package->price !== $oldPrice) {
                    $globalPackageIds[] = $package->id;
                }
            }

            return [$summary, $globalPackageIds];
        }, 3);

        foreach ($updatedGlobalPackageIds as $globalPackageId) {
            $globalPackage = GlobalTopupPackage::query()->find($globalPackageId);

            if ($globalPackage instanceof GlobalTopupPackage) {
                $this->globalPackageSyncService->sync($globalPackage);
            }
        }

        return $summary;
    }

    /**
     * @param  array{minimum_profit_percent: float, enabled: bool, checked: int, adjusted: int, capped: int}  $summary
     * @return array{minimum_profit_percent: float, enabled: bool, checked: int, adjusted: int, capped: int}
     */
    private function adjustPackage(Model $package, float $minimumProfitPercent, array $summary): array
    {
        $providerPrice = (int) $package->getAttribute('provider_price');
        $currentPrice = (int) $package->getAttribute('price');
        $originalPrice = (int) $package->getAttribute('original_price');
        $summary['checked']++;

        if ($providerPrice <= 0 || $currentPrice <= 0) {
            return $summary;
        }

        $minimumProfitBasisPoints = (int) round($minimumProfitPercent * 100);
        $requiredPrice = (int) ceil(($providerPrice * 10000) / (10000 - $minimumProfitBasisPoints));
        $targetPrice = $originalPrice > 0 ? min($requiredPrice, $originalPrice) : $requiredPrice;

        if ($targetPrice < $requiredPrice) {
            $summary['capped']++;
        }

        if ($targetPrice <= $currentPrice) {
            return $summary;
        }

        $package->setAttribute('price', $targetPrice);
        $package->save();
        $summary['adjusted']++;

        return $summary;
    }

    private function minimumProfitPercent(TopupProvider $provider): float
    {
        $configuredPercent = data_get($provider->connection_config, 'minimum_profit_percent', 0);

        if (! is_numeric($configuredPercent)) {
            return 0;
        }

        return min(max((float) $configuredPercent, 0), 99.99);
    }
}
