<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateProgramService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\TopupPackage;
use App\Models\User;

class AffiliateRateCardService
{
    public function __construct(
        private readonly AffiliateProgramService $programService,
        private readonly TopupPackagePricingService $pricingService,
    ) {}

    /** @return array{rates: array<int, array<string, mixed>>} */
    public function data(User $user): array
    {
        $program = $this->programService->enabled();
        abort_unless($program !== null && $program->tenant_id === $user->tenant_id, 404);

        $packageRates = AffiliatePackageRate::query()
            ->where('tenant_id', $user->tenant_id)
            ->get()
            ->keyBy('topup_package_id');
        $globalRates = AffiliateGlobalPackageRate::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('global_topup_package_id');
        $packages = TopupPackage::query()
            ->with(['game:id,name,package_mode,provider_service_code', 'globalTopupPackage.provider', 'globalTopupPackage.gameSettings'])
            ->active()
            ->orderBy('game_id')
            ->orderBy('sort_order')
            ->orderBy('denomination')
            ->get();

        $this->pricingService->apply($packages);

        return [
            'rates' => $packages->map(function (TopupPackage $package) use ($packageRates, $globalRates): ?array {
                if (! $package->getAttribute('is_price_available')) {
                    return null;
                }

                $packageRate = $packageRates->get($package->id);

                if ($packageRate instanceof AffiliatePackageRate && ! $packageRate->is_active) {
                    return null;
                }

                $rate = $packageRate;
                $source = 'package';

                if (! $rate instanceof AffiliatePackageRate
                    && $package->getAttribute('package_source') === 'global'
                    && $package->global_topup_package_id !== null) {
                    $rate = $globalRates->get($package->global_topup_package_id);
                    $source = 'global';
                }

                if (! $rate instanceof AffiliatePackageRate && ! $rate instanceof AffiliateGlobalPackageRate) {
                    return null;
                }

                $sellingPrice = (int) $package->getAttribute('selling_price');
                $isPercentage = $rate->commission_type === AffiliatePackageRate::TYPE_PERCENTAGE;
                $estimatedCommission = $isPercentage
                    ? intdiv($sellingPrice * (int) $rate->percentage_basis_points, 10000)
                    : (int) $rate->fixed_amount;

                return [
                    'package_id' => $package->id,
                    'game' => $package->game?->name,
                    'package' => $package->name,
                    'denomination' => (int) $package->denomination,
                    'selling_price' => $sellingPrice,
                    'commission_type' => $rate->commission_type,
                    'fixed_amount' => $isPercentage ? null : (int) $rate->fixed_amount,
                    'percentage' => $isPercentage ? (int) $rate->percentage_basis_points / 100 : null,
                    'estimated_commission' => $estimatedCommission,
                    'source' => $source,
                ];
            })->filter()->values()->all(),
        ];
    }
}
