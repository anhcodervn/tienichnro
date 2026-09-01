<?php

namespace Database\Factories;

use App\Models\MemberLevel;
use App\Models\MemberLevelPackagePrice;
use App\Models\TopupPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberLevelPackagePrice>
 */
class MemberLevelPackagePriceFactory extends Factory
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
            'topup_package_id' => TopupPackage::factory(),
            'pricing_mode' => 'discount',
            'discount_basis_points' => 100,
            'fixed_price' => null,
            'minimum_profit' => null,
            'is_active' => true,
        ];
    }
}
