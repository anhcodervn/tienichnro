<?php

namespace Database\Factories;

use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\GlobalTopupPackage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateGlobalPackageRate>
 */
class AffiliateGlobalPackageRateFactory extends Factory
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
            'global_topup_package_id' => GlobalTopupPackage::factory(),
            'commission_type' => AffiliatePackageRate::TYPE_FIXED,
            'fixed_amount' => 1000,
            'percentage_basis_points' => null,
            'is_active' => true,
        ];
    }
}
