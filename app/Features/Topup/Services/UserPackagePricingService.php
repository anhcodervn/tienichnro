<?php

namespace App\Features\Topup\Services;

use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;

class UserPackagePricingService
{
    /** @var array<int, array<int, UserPackagePrice|null>> */
    private array $rules = [];

    /** @var array<int, array<int, UserGlobalPackagePrice|null>> */
    private array $globalRules = [];

    /** @param array<int, int> $packageIds */
    public function prime(User $user, array $packageIds): void
    {
        $rules = UserPackagePrice::query()
            ->where('user_id', $user->id)
            ->whereIn('topup_package_id', $packageIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('topup_package_id');

        foreach ($packageIds as $packageId) {
            $this->rules[$user->id][$packageId] = $rules->get($packageId);
        }
    }

    /** @param array<int, int> $globalPackageIds */
    public function primeGlobal(User $user, array $globalPackageIds): void
    {
        $globalPackageIds = array_values(array_unique(array_filter($globalPackageIds)));

        if ($globalPackageIds === []) {
            return;
        }

        $rules = UserGlobalPackagePrice::query()
            ->where('user_id', $user->id)
            ->whereIn('global_topup_package_id', $globalPackageIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('global_topup_package_id');

        foreach ($globalPackageIds as $globalPackageId) {
            $this->globalRules[$user->id][$globalPackageId] = $rules->get($globalPackageId);
        }
    }

    /** @return array{price:int,discount_amount:int,pricing_mode:string,pricing_source:string,discount_basis_points:int,minimum_profit:int} */
    public function resolve(?User $user, TopupPackage $package, int $basePrice, ?int $costFloor): array
    {
        if (! $user instanceof User) {
            return $this->result($basePrice, $basePrice, 'standard', 'standard', 0, 0);
        }

        if (! array_key_exists($package->id, $this->rules[$user->id] ?? [])) {
            $this->prime($user, [$package->id]);
        }

        $rule = $this->rules[$user->id][$package->id];
        $pricingSource = 'package';

        if (! $rule instanceof UserPackagePrice) {
            $globalPackageId = (int) ($package->global_topup_package_id ?? 0);

            if ($globalPackageId <= 0 || $package->game?->package_mode !== 'global') {
                return $this->result($basePrice, $basePrice, 'standard', 'standard', 0, 0);
            }

            return $this->resolveGlobal($user, $globalPackageId, $basePrice, $costFloor);
        }

        return $this->applyRule($rule, $basePrice, $costFloor, $pricingSource);
    }

    /** @return array{price:int,discount_amount:int,pricing_mode:string,pricing_source:string,discount_basis_points:int,minimum_profit:int} */
    public function resolveGlobal(?User $user, GlobalTopupPackage|int $globalPackage, int $basePrice, ?int $costFloor): array
    {
        if (! $user instanceof User) {
            return $this->result($basePrice, $basePrice, 'standard', 'standard', 0, 0);
        }

        $globalPackageId = $globalPackage instanceof GlobalTopupPackage ? $globalPackage->id : $globalPackage;

        if (! array_key_exists($globalPackageId, $this->globalRules[$user->id] ?? [])) {
            $this->primeGlobal($user, [$globalPackageId]);
        }

        $rule = $this->globalRules[$user->id][$globalPackageId];

        if (! $rule instanceof UserGlobalPackagePrice) {
            return $this->result($basePrice, $basePrice, 'standard', 'standard', 0, 0);
        }

        return $this->applyRule($rule, $basePrice, $costFloor, 'global');
    }

    /** @return array{price:int,discount_amount:int,pricing_mode:string,pricing_source:string,discount_basis_points:int,minimum_profit:int} */
    private function applyRule(
        UserPackagePrice|UserGlobalPackagePrice $rule,
        int $basePrice,
        ?int $costFloor,
        string $pricingSource,
    ): array {
        $discountBasisPoints = (int) ($rule->discount_basis_points ?? 0);
        $pricingMode = $rule->pricing_mode;
        $candidatePrice = $pricingMode === UserPackagePrice::MODE_FIXED
            ? (int) ($rule->fixed_price ?? $basePrice)
            : $basePrice - intdiv($basePrice * $discountBasisPoints, 10000);
        $minimumProfit = (int) $rule->minimum_profit;
        $minimumPrice = $costFloor === null ? 0 : $costFloor + $minimumProfit;
        $finalPrice = min($basePrice, max(0, $candidatePrice, $minimumPrice));

        return $this->result($basePrice, $finalPrice, $pricingMode, $pricingSource, $discountBasisPoints, $minimumProfit);
    }

    /** @return array{price:int,discount_amount:int,pricing_mode:string,pricing_source:string,discount_basis_points:int,minimum_profit:int} */
    private function result(
        int $basePrice,
        int $finalPrice,
        string $pricingMode,
        string $pricingSource,
        int $discountBasisPoints,
        int $minimumProfit,
    ): array {
        return [
            'price' => $finalPrice,
            'discount_amount' => $basePrice - $finalPrice,
            'pricing_mode' => $pricingMode,
            'pricing_source' => $pricingSource,
            'discount_basis_points' => $discountBasisPoints,
            'minimum_profit' => $minimumProfit,
        ];
    }
}
