<?php

namespace Database\Factories;

use App\Models\GamePasswordOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GamePasswordOrder> */
class GamePasswordOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'request_id' => fake()->uuid(), 'row_index' => 0, 'payload_hash' => hash('sha256', fake()->uuid()),
            'username' => fake()->unique()->userName(), 'server_code' => 3, 'old_password' => 'old-password', 'new_password' => 'new-password', 'charged_amount' => 0];
    }
}
