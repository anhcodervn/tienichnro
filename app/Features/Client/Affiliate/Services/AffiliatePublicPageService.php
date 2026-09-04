<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProgram;

class AffiliatePublicPageService
{
    /** @return array<string, mixed> */
    public function data(AffiliateProgram $program): array
    {
        $rates = AffiliatePackageRate::query()
            ->where('tenant_id', $program->tenant_id)
            ->where('is_active', true)
            ->whereHas('package', fn ($query) => $query->where('status', 'active'))
            ->with(['package:id,game_id,name,status', 'package.game:id,name'])
            ->orderBy('topup_package_id')
            ->get()
            ->map(fn (AffiliatePackageRate $rate): array => [
                'game' => $rate->package?->game?->name,
                'package' => $rate->package?->name,
                'type' => $rate->commission_type,
                'fixed_amount' => $rate->fixed_amount,
                'percentage' => $rate->percentage_basis_points === null
                    ? null
                    : $rate->percentage_basis_points / 100,
            ])
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
