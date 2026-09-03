<?php

namespace Database\Factories;

use App\Models\RechargeBonusTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RechargeBonusTier>
 */
class RechargeBonusTierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'minimum_amount' => fake()->unique()->randomElement([100_000, 200_000, 500_000, 1_000_000, 2_000_000]),
            'bonus_basis_points' => fake()->numberBetween(100, 2_000),
            'is_active' => true,
        ];
    }
}
