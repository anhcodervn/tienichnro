<?php

namespace Database\Factories;

use App\Models\GameServiceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameServiceOrder>
 */
class GameServiceOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->safeEmail(),
            'game_name' => fake()->words(2, true),
            'service_name' => fake()->words(3, true),
            'package_name' => fake()->words(3, true),
            'price_label' => 'Mặc định',
            'server_name' => fake()->word(),
            'payload' => ['character_name' => fake()->userName()],
            'quantity' => 1,
            'unit_price' => 50000,
            'total_amount' => 50000,
            'status' => 'pending',
        ];
    }
}
