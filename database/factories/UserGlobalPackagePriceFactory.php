<?php

namespace Database\Factories;

use App\Models\GlobalTopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserGlobalPackagePrice>
 */
class UserGlobalPackagePriceFactory extends Factory
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
            'global_topup_package_id' => GlobalTopupPackage::factory(),
            'pricing_mode' => UserGlobalPackagePrice::MODE_DISCOUNT,
            'discount_basis_points' => 500,
            'fixed_price' => null,
            'minimum_profit' => 0,
            'is_active' => true,
        ];
    }
}
