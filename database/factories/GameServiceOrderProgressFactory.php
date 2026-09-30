<?php

namespace Database\Factories;

use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameServiceOrderProgress>
 */
class GameServiceOrderProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_service_order_id' => GameServiceOrder::factory(),
            'user_id' => User::factory(),
            'type' => GameServiceOrderProgress::TYPE_PROGRESS,
            'description' => fake()->sentence(),
            'image_path' => null,
        ];
    }
}
