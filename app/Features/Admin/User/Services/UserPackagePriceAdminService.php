<?php

namespace App\Features\Admin\User\Services;

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Features\Topup\Services\UserPackagePricingService;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPrice;
use App\Models\UserPackagePrice;
use App\Utils\Site;

class UserPackagePriceAdminService
{
    public function __construct(
        private readonly TopupPackagePricingService $topupPackagePricingService,
        private readonly UserPackagePricingService $userPackagePricingService,
    ) {}

    /** @return array{prices:array<int, array<string, mixed>>,global_price:array<string, mixed>} */
    public function catalog(User $user): array
    {
        $packages = TopupPackage::query()
            ->with('game:id,name')
            ->where('status', 'active')
            ->orderBy('game_id')
            ->orderBy('denomination')
            ->get();
        $this->topupPackagePricingService->apply($packages);
        $this->userPackagePricingService->prime($user, $packages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        $rules = UserPackagePrice::query()->where('user_id', $user->id)->get()->keyBy('topup_package_id');

        $prices = $packages
            ->filter(fn (TopupPackage $package): bool => (bool) $package->is_price_available)
            ->map(function (TopupPackage $package) use ($rules, $user): array {
                $rule = $rules->get($package->id);
                $basePrice = (int) $package->selling_price;
                $costFloor = Site::isMain()
                    ? ($package->provider_price === null ? null : (int) $package->provider_price)
                    : (int) $package->tenant_cost_price;
                $price = $this->userPackagePricingService->resolve($user, $package, $basePrice, $costFloor);

                return [
                    'package_id' => $package->id,
                    'game' => $package->game?->name,
                    'package' => $package->name,
                    'denomination' => (int) $package->denomination,
                    'base_price' => $basePrice,
                    'member_price' => $price['price'],
                    'discount_amount' => $price['discount_amount'],
                    'pricing_mode' => $rule?->pricing_mode ?? UserPackagePrice::MODE_DISCOUNT,
                    'discount_percent' => $rule?->discount_basis_points === null ? 0 : $rule->discount_basis_points / 100,
                    'fixed_price' => $rule?->fixed_price,
                    'minimum_profit' => (int) ($rule?->minimum_profit ?? 0),
                    'is_active' => (bool) ($rule?->is_active ?? false),
                    'pricing_source' => $price['pricing_source'],
                ];
            })
            ->values()
            ->all();

        $globalRule = UserGlobalPrice::query()->where('user_id', $user->id)->first();

        return [
            'prices' => $prices,
            'global_price' => [
                'discount_percent' => $globalRule?->discount_basis_points === null ? 0 : $globalRule->discount_basis_points / 100,
                'minimum_profit' => (int) ($globalRule?->minimum_profit ?? 0),
                'is_active' => (bool) ($globalRule?->is_active ?? false),
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    public function save(User $user, TopupPackage $package, array $payload): UserPackagePrice
    {
        return UserPackagePrice::query()->updateOrCreate(
            ['user_id' => $user->id, 'topup_package_id' => $package->id],
            $this->priceAttributes($payload),
        );
    }

    /** @param array<string, mixed> $payload */
    public function saveGlobal(User $user, array $payload): UserGlobalPrice
    {
        return UserGlobalPrice::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'discount_basis_points' => (int) round((float) $payload['discount_percent'] * 100),
                'minimum_profit' => (int) $payload['minimum_profit'],
                'is_active' => (bool) $payload['is_active'],
            ],
        );
    }

    public function delete(User $user, TopupPackage $package): void
    {
        UserPackagePrice::query()
            ->where('user_id', $user->id)
            ->where('topup_package_id', $package->id)
            ->delete();
    }

    public function deleteGlobal(User $user): void
    {
        UserGlobalPrice::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function priceAttributes(array $payload): array
    {
        return [
            'pricing_mode' => $payload['pricing_mode'],
            'discount_basis_points' => $payload['pricing_mode'] === UserPackagePrice::MODE_DISCOUNT
                ? (int) round((float) $payload['discount_percent'] * 100)
                : null,
            'fixed_price' => $payload['pricing_mode'] === UserPackagePrice::MODE_FIXED
                ? (int) $payload['fixed_price']
                : null,
            'minimum_profit' => (int) $payload['minimum_profit'],
            'is_active' => (bool) $payload['is_active'],
        ];
    }
}
