<?php

namespace Database\Factories;

use App\Models\GlobalTopupPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalTopupPackage>
 */
class GlobalTopupPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $denomination = fake()->unique()->numberBetween(1, 999999) * 1000;

        return [
            'name' => 'Gói Global '.number_format($denomination),
            'code' => fake()->unique()->slug(2),
            'denomination' => $denomination,
            'price' => (int) round($denomination * 0.95),
            'original_price' => $denomination,
            'description' => null,
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
