<?php

namespace Database\Factories;

use App\Models\AffiliatePackageRate;
use App\Models\Tenant;
use App\Models\TopupPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliatePackageRate>
 */
class AffiliatePackageRateFactory extends Factory
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
            'commission_type' => AffiliatePackageRate::TYPE_FIXED,
            'fixed_amount' => 1000,
            'percentage_basis_points' => null,
            'is_active' => true,
        ];
    }
}
