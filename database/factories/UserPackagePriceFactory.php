<?php

namespace Database\Factories;

use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserPackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPackagePrice>
 */
class UserPackagePriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'topup_package_id' => TopupPackage::factory(),
            'pricing_mode' => UserPackagePrice::MODE_DISCOUNT,
            'discount_basis_points' => 500,
            'fixed_price' => null,
            'minimum_profit' => 0,
            'is_active' => true,
        ];
    }
}
