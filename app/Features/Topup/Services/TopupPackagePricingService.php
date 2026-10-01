<?php

namespace App\Features\Topup\Services;

use App\Features\Tenant\Services\TenantPriceService;
use App\Models\TopupPackage;
use App\Models\User;
use App\Utils\Site;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class TopupPackagePricingService
{
    public function __construct(
        private readonly TopupPackagePriceSourceService $priceSourceService,
        private readonly TenantPriceService $tenantPriceService,
        private readonly UserPackagePricingService $userPackagePricingService,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(TopupPackage $package, ?User $user = null): array
    {
        return $this->applyTenantPrice($package, $this->priceSourceService->resolve($package), $user);
    }

    /** @param Collection<int, TopupPackage> $packages */
    public function apply(Collection $packages, ?User $user = null): void
    {
        (new EloquentCollection($packages->all()))->loadMissing(['game', 'globalTopupPackage']);
        $sources = $packages->mapWithKeys(fn (TopupPackage $package): array => [
            $package->id => $this->priceSourceService->resolveOrNull($package),
        ]);
        $tenant = Site::mySite();

        if ($tenant !== null && ! $tenant->is_main) {
            $this->tenantPriceService->prime($tenant, $packages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());
        }

        $packageIds = $packages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $globalPackageIds = $packages->pluck('global_topup_package_id')->filter()->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $billingUser = $tenant !== null && ! $tenant->is_main ? $tenant->billingUser()->first() : null;

        if ($billingUser instanceof User) {
            $this->userPackagePricingService->prime($billingUser, $packageIds);
            $this->userPackagePricingService->primeGlobal($billingUser, $globalPackageIds);
        }

        if ($user instanceof User) {
            $this->userPackagePricingService->prime($user, $packageIds);
            $this->userPackagePricingService->primeGlobal($user, $globalPackageIds);
        }

        foreach ($packages as $package) {
            $source = $sources->get($package->id);

            if (! is_array($source)) {
                $package->setAttribute('is_price_available', false);

                continue;
            }

            $price = $this->applyTenantPrice($package, $source, $user, $billingUser);
            $package->setAttribute('is_price_available', true);
            $package->setAttribute('retail_price', $price['retail_price']);
            $package->setAttribute('selling_price', $price['final_price']);
            $package->setAttribute('tenant_cost_price', $price['tenant_cost_price']);
            $package->setAttribute('tenant_profit', $price['tenant_profit']);
            $package->setAttribute('tenant_pricing_mode', $price['tenant_pricing_mode']);
            $package->setAttribute('user_pricing_mode', $price['user_pricing_mode']);
            $package->setAttribute('user_pricing_source', $price['user_pricing_source']);
            $package->setAttribute('user_discount_amount', $price['user_discount_amount']);
            $package->setAttribute('package_source', $price['package_source']);
            $package->setAttribute('price', $price['final_price']);
            $package->setAttribute('original_price', $price['original_price']);
            $package->setAttribute('discount_percent', $package->calculateDiscountPercent());
        }
    }

    /** @param array<string, mixed> $source @return array<string, mixed> */
    private function applyTenantPrice(TopupPackage $package, array $source, ?User $user, ?User $billingUser = null): array
    {
        $basePrice = (int) $source['retail_price'];
        $tenant = Site::mySite();

        if ($tenant === null) {
            return [
                ...$source,
                'final_price' => $basePrice,
                'tenant_cost_price' => $basePrice,
                'tenant_profit' => 0,
                'tenant_pricing_mode' => 'base_price',
                'user_pricing_mode' => 'standard',
                'user_pricing_source' => 'standard',
                'user_discount_amount' => 0,
            ];
        }

        $providerPrice = $source['provider_price'] === null ? null : (int) $source['provider_price'];

        if ($tenant->is_main) {
            $userPrice = $this->userPackagePricingService->resolve($user, $package, $basePrice, $providerPrice);

            return [
                ...$source,
                'final_price' => $userPrice['price'],
                'tenant_cost_price' => $userPrice['price'],
                'tenant_profit' => 0,
                'tenant_pricing_mode' => 'base_price',
                'user_pricing_mode' => $userPrice['pricing_mode'],
                'user_pricing_source' => $userPrice['pricing_source'],
                'user_discount_amount' => $userPrice['discount_amount'],
            ];
        }

        $billingUser ??= $tenant->billingUser()->first();
        $billingPrice = $this->userPackagePricingService->resolve($billingUser, $package, $basePrice, $providerPrice);
        $tenantPrice = $this->tenantPriceService->resolve($tenant, $package, $billingPrice['price']);
        $userPrice = $this->userPackagePricingService->resolve(
            $user,
            $package,
            $tenantPrice['selling_price'],
            $billingPrice['price'],
        );

        return [
            ...$source,
            'retail_price' => $tenantPrice['selling_price'],
            'final_price' => $userPrice['price'],
            'tenant_cost_price' => $billingPrice['price'],
            'tenant_profit' => $userPrice['price'] - $billingPrice['price'],
            'tenant_pricing_mode' => $tenantPrice['pricing_mode'],
            'user_pricing_mode' => $userPrice['pricing_mode'],
            'user_pricing_source' => $userPrice['pricing_source'],
            'user_discount_amount' => $userPrice['discount_amount'],
        ];
    }
}
