<?php

namespace Database\Factories;

use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameServicePackagePrice>
 */
class GameServicePackagePriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_service_package_id' => GameServicePackage::factory(),
            'label' => fake()->unique()->words(2, true),
            'code' => fake()->unique()->bothify('price-###??'),
            'price' => fake()->numberBetween(10000, 1000000),
            'collaborator_price' => fake()->numberBetween(5000, 9000),
            'original_price' => null,
            'quantity_enabled' => false,
            'min_quantity' => 1,
            'max_quantity' => 1,
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
