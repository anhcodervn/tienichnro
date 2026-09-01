<?php

namespace Database\Factories;

use App\Models\GlobalTopupPackage;
use App\Models\MemberLevel;
use App\Models\MemberLevelGlobalPackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberLevelGlobalPackagePrice>
 */
class MemberLevelGlobalPackagePriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_level_id' => MemberLevel::factory(),
            'global_topup_package_id' => GlobalTopupPackage::factory(),
            'pricing_mode' => 'discount',
            'discount_basis_points' => 100,
            'fixed_price' => null,
            'minimum_profit' => null,
            'is_active' => true,
        ];
    }
}
