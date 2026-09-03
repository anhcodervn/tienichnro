<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserGlobalPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserGlobalPrice> */
class UserGlobalPriceFactory extends Factory
{
    protected $model = UserGlobalPrice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'discount_basis_points' => 500,
            'minimum_profit' => 0,
            'is_active' => true,
        ];
    }
}
