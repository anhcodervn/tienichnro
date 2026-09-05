<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProgram;
use App\Models\TopupPackage;

class AffiliatePublicPageService
{
    /** @return array<string, mixed> */
    public function data(AffiliateProgram $program): array
    {
        $packageRates = AffiliatePackageRate::query()
            ->where('tenant_id', $program->tenant_id)
            ->get()
            ->keyBy('topup_package_id');
        $globalRates = AffiliateGlobalPackageRate::query()
            ->where('tenant_id', $program->tenant_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('global_topup_package_id');
        $rates = TopupPackage::query()
            ->where('status', 'active')
            ->with('game:id,name,package_mode')
            ->orderBy('game_id')
            ->orderBy('denomination')
            ->get()
            ->map(function (TopupPackage $package) use ($packageRates, $globalRates): ?array {
                $packageRate = $packageRates->get($package->id);

                if ($packageRate instanceof AffiliatePackageRate && ! $packageRate->is_active) {
                    return null;
                }

                $rate = $packageRate;

                if ($rate === null && $package->game?->package_mode === 'global' && $package->global_topup_package_id) {
                    $rate = $globalRates->get($package->global_topup_package_id);
                }

                if (! $rate instanceof AffiliatePackageRate && ! $rate instanceof AffiliateGlobalPackageRate) {
                    return null;
                }

                return [
                    'game' => $package->game?->name,
                    'package' => $package->name,
                    'type' => $rate->commission_type,
                    'fixed_amount' => $rate->fixed_amount,
                    'percentage' => $rate->percentage_basis_points === null
                        ? null
                        : $rate->percentage_basis_points / 100,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'affiliatePolicy' => [
                'holding_days' => 7,
                'minimum_conversion' => AffiliateWalletService::MINIMUM_CONVERSION,
                'minimum_withdrawal' => $program->minimum_withdrawal,
                'referral_cookie_days' => 30,
            ],
            'affiliateRates' => $rates,
        ];
    }
}
