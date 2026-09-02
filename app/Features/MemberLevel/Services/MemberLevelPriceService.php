<?php

namespace App\Features\MemberLevel\Services;

use App\Features\Topup\Services\TopupPackagePriceSourceService;
use App\Models\MemberLevel;
use App\Models\MemberLevelGlobalPackagePrice;
use App\Models\MemberLevelPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class MemberLevelPriceService
{
    public function __construct(
        private readonly MemberLevelService $memberLevelService,
        private readonly TopupPackagePriceSourceService $priceSourceService,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(TopupPackage $package, ?User $user): array
    {
        $status = $this->memberLevelService->status($user);
        $levelId = $status['effective_level']['id'] ?? null;
        $level = is_int($levelId) ? MemberLevel::query()->find($levelId) : null;
        $source = $this->priceSourceService->resolve($package);
        $override = $this->resolveOverride($package, $source, $level);

        return $this->calculate($package, $source, $level, $override, $status);
    }

    /** @param Collection<int, TopupPackage> $packages */
    public function apply(Collection $packages, ?User $user): ?array
    {
        (new EloquentCollection($packages->all()))->loadMissing(['game', 'globalTopupPackage']);
        $status = $this->memberLevelService->status($user);
        $levelId = $status['effective_level']['id'] ?? null;
        $level = is_int($levelId) ? MemberLevel::query()->find($levelId) : null;
        $sources = $packages->mapWithKeys(fn (TopupPackage $package): array => [
            $package->id => $this->priceSourceService->resolveOrNull($package),
        ]);
        $packageOverrides = $level instanceof MemberLevel
            ? MemberLevelPackagePrice::query()
                ->where('member_level_id', $level->id)
                ->whereIn('topup_package_id', $sources->filter(fn (?array $source): bool => $source !== null && $source['package_source'] === 'custom')->keys())
                ->where('is_active', true)
                ->get()
                ->keyBy('topup_package_id')
            : collect();
        $globalPackageOverrides = $level instanceof MemberLevel
            ? MemberLevelGlobalPackagePrice::query()
                ->where('member_level_id', $level->id)
                ->whereIn('global_topup_package_id', $sources->filter()->pluck('global_topup_package_id')->filter()->unique())
                ->where('is_active', true)
                ->get()
                ->keyBy('global_topup_package_id')
            : collect();

        foreach ($packages as $package) {
            $source = $sources->get($package->id);

            if (! is_array($source)) {
                $package->setAttribute('is_price_available', false);

                continue;
            }

            $override = $source['package_source'] === 'global'
                ? $globalPackageOverrides->get($source['global_topup_package_id'])
                : $packageOverrides->get($package->id);
            $price = $this->calculate($package, $source, $level, $override, $status);
            $package->setAttribute('is_price_available', true);
            $package->setAttribute('retail_price', $price['retail_price']);
            $package->setAttribute('member_price', $price['final_price']);
            $package->setAttribute('member_level_discount_amount', $price['discount_amount']);
            $package->setAttribute('member_level_name', $price['level_name']);
            $package->setAttribute('member_level_discount_bps', $price['discount_basis_points']);
            $package->setAttribute('package_source', $price['package_source']);
            $package->setAttribute('price', $price['final_price']);
            $package->setAttribute('original_price', $price['original_price']);
            $package->setAttribute('discount_percent', $package->calculateDiscountPercent());
        }

        return $status;
    }

    /** @return array<string, mixed> */
    private function calculate(
        TopupPackage $package,
        array $source,
        ?MemberLevel $level,
        MemberLevelPackagePrice|MemberLevelGlobalPackagePrice|null $override,
        ?array $status,
    ): array {
        $retailPrice = (int) $source['retail_price'];
        $pricingMode = 'retail';
        $discountBasisPoints = 0;
        $candidatePrice = $retailPrice;

        if ($override !== null && $override->pricing_mode === 'fixed' && $override->fixed_price !== null) {
            $pricingMode = 'fixed';
            $candidatePrice = $override->fixed_price;
        } elseif ($level instanceof MemberLevel) {
            $pricingMode = 'discount';
            $discountBasisPoints = $override?->discount_basis_points ?? $level->default_discount_bps;
            $candidatePrice = $retailPrice - intdiv($retailPrice * $discountBasisPoints, 10000);
        }

        $minimumProfit = $override?->minimum_profit ?? $level?->minimum_profit ?? 0;
        $providerPrice = $source['provider_price'];
        $priceFloor = $providerPrice === null ? $retailPrice : $providerPrice + $minimumProfit;
        $finalPrice = min($retailPrice, max(0, $candidatePrice, $priceFloor));

        return [
            'level_id' => $level?->id,
            'level_name' => $level?->name,
            'level_status' => $status,
            'pricing_mode' => $pricingMode,
            'discount_basis_points' => $discountBasisPoints,
            'retail_price' => $retailPrice,
            'final_price' => $finalPrice,
            'discount_amount' => $retailPrice - $finalPrice,
            'minimum_profit' => $minimumProfit,
            'provider_price' => $providerPrice,
            'original_price' => (int) $source['original_price'],
            'package_source' => $source['package_source'],
            'global_topup_package_id' => $source['global_topup_package_id'],
            'global_topup_package_name' => $source['global_topup_package_name'],
        ];
    }

    private function resolveOverride(TopupPackage $package, array $source, ?MemberLevel $level): MemberLevelPackagePrice|MemberLevelGlobalPackagePrice|null
    {
        if (! $level instanceof MemberLevel) {
            return null;
        }

        if ($source['package_source'] === 'global') {
            return MemberLevelGlobalPackagePrice::query()
                ->where('member_level_id', $level->id)
                ->where('global_topup_package_id', $source['global_topup_package_id'])
                ->where('is_active', true)
                ->first();
        }

        return MemberLevelPackagePrice::query()
            ->where('member_level_id', $level->id)
            ->where('topup_package_id', $package->id)
            ->where('is_active', true)
            ->first();
    }
}
