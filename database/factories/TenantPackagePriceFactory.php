<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPackagePrice>
 */
class TenantPackagePriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'topup_package_id' => TopupPackage::factory(),
            'pricing_mode' => TenantPackagePrice::MODE_MARKUP_AMOUNT,
            'fixed_price' => null,
            'markup_amount' => 5000,
            'markup_basis_points' => null,
            'is_active' => true,
        ];
    }
}
