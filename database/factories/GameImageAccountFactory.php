<?php

namespace Database\Factories;

use App\Models\GameImageAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameImageAccount>
 */
class GameImageAccountFactory extends Factory
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
            'username' => fake()->unique()->userName(),
            'password' => 'game-test-password',
            'server' => 'Vũ trụ 1',
        ];
    }
}
