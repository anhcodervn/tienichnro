<?php

namespace Database\Factories;

use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameServiceOrderMessage>
 */
class GameServiceOrderMessageFactory extends Factory
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
            'sender_id' => User::factory(),
            'sender_role' => GameServiceOrderMessage::ROLE_USER,
            'message' => fake()->sentence(),
        ];
    }
}
