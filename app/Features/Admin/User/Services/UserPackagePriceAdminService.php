<?php

namespace App\Features\Admin\User\Services;

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Features\Topup\Services\UserPackagePricingService;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;
use App\Utils\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPackagePriceAdminService
{
    public function __construct(
        private readonly TopupPackagePricingService $topupPackagePricingService,
        private readonly UserPackagePricingService $userPackagePricingService,
    ) {}

    /** @return array{prices:array<int, array<string, mixed>>,global_packages:array<int, array<string, mixed>>} */
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
                $memberPrice = (int) $price['price'];

                return [
                    'package_id' => $package->id,
                    'game_id' => $package->game_id,
                    'game' => $package->game?->name,
                    'package' => $package->name,
                    'denomination' => (int) $package->denomination,
                    'cost_price' => $costFloor,
                    'base_price' => $basePrice,
                    'base_profit' => $costFloor === null ? null : $basePrice - $costFloor,
                    'member_price' => $memberPrice,
                    'member_profit' => $costFloor === null ? null : $memberPrice - $costFloor,
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

        $globalPackages = GlobalTopupPackage::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('denomination')
            ->get();
        $this->userPackagePricingService->primeGlobal(
            $user,
            $globalPackages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        );
        $globalRules = UserGlobalPackagePrice::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('global_topup_package_id');
        $globalPrices = $globalPackages
            ->map(function (GlobalTopupPackage $globalPackage) use ($globalRules, $user): array {
                $rule = $globalRules->get($globalPackage->id);
                $basePrice = (int) $globalPackage->price;
                $price = $this->userPackagePricingService->resolveGlobal(
                    $user,
                    $globalPackage,
                    $basePrice,
                    (int) $globalPackage->provider_price,
                );
                $costPrice = Site::isMain() ? (int) $globalPackage->provider_price : null;
                $memberPrice = (int) $price['price'];

                return [
                    'id' => $globalPackage->id,
                    'name' => $globalPackage->name,
                    'denomination' => (int) $globalPackage->denomination,
                    'cost_price' => $costPrice,
                    'base_price' => $basePrice,
                    'base_profit' => $costPrice === null ? null : $basePrice - $costPrice,
                    'member_price' => $memberPrice,
                    'member_profit' => $costPrice === null ? null : $memberPrice - $costPrice,
                    'discount_amount' => $price['discount_amount'],
                    'pricing_mode' => $rule?->pricing_mode ?? UserGlobalPackagePrice::MODE_DISCOUNT,
                    'discount_percent' => $rule?->discount_basis_points === null ? 0 : $rule->discount_basis_points / 100,
                    'fixed_price' => $rule?->fixed_price,
                    'minimum_profit' => (int) ($rule?->minimum_profit ?? 0),
                    'is_active' => (bool) ($rule?->is_active ?? false),
                ];
            })
            ->values()
            ->all();

        return [
            'prices' => $prices,
            'global_packages' => $globalPrices,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function save(User $user, TopupPackage $package, array $payload): UserPackagePrice
    {
        $this->validatePackageProfit($package, $payload);

        return UserPackagePrice::query()->updateOrCreate(
            ['user_id' => $user->id, 'topup_package_id' => $package->id],
            $this->priceAttributes($payload),
        );
    }

    /** @param array<string, mixed> $payload */
    public function saveGlobal(User $user, GlobalTopupPackage $globalPackage, array $payload): UserGlobalPackagePrice
    {
        $this->validateGlobalProfit($globalPackage, $payload);

        return UserGlobalPackagePrice::query()->updateOrCreate(
            ['user_id' => $user->id, 'global_topup_package_id' => $globalPackage->id],
            $this->priceAttributes($payload),
        );
    }

    public function delete(User $user, TopupPackage $package): void
    {
        UserPackagePrice::query()
            ->where('user_id', $user->id)
            ->where('topup_package_id', $package->id)
            ->delete();
    }

    public function deleteGlobal(User $user, GlobalTopupPackage $globalPackage): void
    {
        UserGlobalPackagePrice::query()
            ->where('user_id', $user->id)
            ->where('global_topup_package_id', $globalPackage->id)
            ->delete();
    }

    /** @param array<string, mixed> $payload */
    public function quickSet(User $user, array $payload): void
    {
        $packageIds = collect($payload['package_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();

        if ($payload['scope'] === 'global') {
            $packages = GlobalTopupPackage::query()
                ->whereIn('id', $packageIds)
                ->where('status', 'active')
                ->get(['id', 'provider_price', 'price']);

            $this->assertCompleteQuickSetSelection($packageIds->all(), $packages->pluck('id')->all());
            $this->assertProfitModeHasCost($payload, ! Site::isMain() || $packages->contains(fn (GlobalTopupPackage $package): bool => $package->provider_price === null));
            $this->assertQuickProfitFitsMargins(
                $payload,
                $packages->contains(fn (GlobalTopupPackage $package): bool => (int) ($payload['profit_amount'] ?? 0) > (int) $package->price - (int) $package->provider_price),
            );

            DB::transaction(function () use ($user, $packages, $payload): void {
                foreach ($packages as $package) {
                    UserGlobalPackagePrice::query()->updateOrCreate(
                        ['user_id' => $user->id, 'global_topup_package_id' => $package->id],
                        $this->quickPriceAttributes($payload),
                    );
                }
            });

            return;
        }

        $packages = TopupPackage::query()
            ->with(['game', 'globalTopupPackage'])
            ->whereIn('id', $packageIds)
            ->where('status', 'active')
            ->get();
        $this->topupPackagePricingService->apply($packages);

        $this->assertCompleteQuickSetSelection($packageIds->all(), $packages->pluck('id')->all());
        $this->assertProfitModeHasCost($payload, $packages->contains(fn (TopupPackage $package): bool => $this->packageCost($package) === null));
        $this->assertQuickProfitFitsMargins(
            $payload,
            $packages->contains(function (TopupPackage $package) use ($payload): bool {
                $cost = $this->packageCost($package);

                return $cost !== null && (int) ($payload['profit_amount'] ?? 0) > (int) $package->selling_price - $cost;
            }),
        );

        DB::transaction(function () use ($user, $packages, $payload): void {
            foreach ($packages as $package) {
                UserPackagePrice::query()->updateOrCreate(
                    ['user_id' => $user->id, 'topup_package_id' => $package->id],
                    $this->quickPriceAttributes($payload),
                );
            }
        });
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

    private function packageCost(TopupPackage $package): ?int
    {
        if (! Site::isMain()) {
            return (int) $package->tenant_cost_price;
        }

        return $package->provider_price === null ? null : (int) $package->provider_price;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function quickPriceAttributes(array $payload): array
    {
        $isDiscount = $payload['pricing_mode'] === UserPackagePrice::MODE_DISCOUNT;

        return [
            'pricing_mode' => $payload['pricing_mode'],
            'discount_basis_points' => $isDiscount ? (int) round((float) $payload['discount_percent'] * 100) : null,
            'fixed_price' => null,
            'minimum_profit' => $isDiscount ? 0 : (int) $payload['profit_amount'],
            'is_active' => (bool) $payload['is_active'],
        ];
    }

    /** @param array<int, int> $requestedIds @param array<int, int> $foundIds */
    private function assertCompleteQuickSetSelection(array $requestedIds, array $foundIds): void
    {
        if (count($requestedIds) !== count($foundIds)) {
            throw ValidationException::withMessages([
                'package_ids' => 'Danh sách gói chứa gói không tồn tại hoặc đã ngừng hoạt động.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertProfitModeHasCost(array $payload, bool $hasMissingCost): void
    {
        if ($payload['pricing_mode'] === UserPackagePrice::MODE_PROFIT && $hasMissingCost) {
            throw ValidationException::withMessages([
                'pricing_mode' => 'Không thể tính theo lợi nhuận vì có gói chưa cấu hình giá provider.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertQuickProfitFitsMargins(array $payload, bool $exceedsMargin, string $field = 'profit_amount'): void
    {
        if ($payload['pricing_mode'] === UserPackagePrice::MODE_PROFIT && $exceedsMargin) {
            throw ValidationException::withMessages([
                $field => 'Mức lãi mới không được cao hơn lãi của giá bán hiện tại.',
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function validatePackageProfit(TopupPackage $package, array $payload): void
    {
        if ($payload['pricing_mode'] !== UserPackagePrice::MODE_PROFIT) {
            return;
        }

        $price = $this->topupPackagePricingService->resolve($package);
        $cost = Site::isMain()
            ? ($price['provider_price'] === null ? null : (int) $price['provider_price'])
            : (int) $price['tenant_cost_price'];

        $this->assertProfitModeHasCost($payload, $cost === null);
        $this->assertQuickProfitFitsMargins(
            $payload,
            $cost !== null && (int) $payload['minimum_profit'] > (int) $price['final_price'] - $cost,
            'minimum_profit',
        );
    }

    /** @param array<string, mixed> $payload */
    private function validateGlobalProfit(GlobalTopupPackage $package, array $payload): void
    {
        if ($payload['pricing_mode'] !== UserPackagePrice::MODE_PROFIT) {
            return;
        }

        $this->assertProfitModeHasCost($payload, ! Site::isMain() || $package->provider_price === null);
        $this->assertQuickProfitFitsMargins(
            $payload,
            (int) $payload['minimum_profit'] > (int) $package->price - (int) $package->provider_price,
            'minimum_profit',
        );
    }
}
