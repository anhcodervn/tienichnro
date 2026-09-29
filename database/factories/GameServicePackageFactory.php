<?php

namespace Database\Factories;

use App\Models\GameService;
use App\Models\GameServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameServicePackage>
 */
class GameServicePackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_service_id' => GameService::factory(),
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->bothify('package-###??'),
            'description' => fake()->sentence(),
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
