<?php

namespace Database\Factories;

use App\Models\GameVerificationOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameVerificationOrder>
 */
class GameVerificationOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'request_id' => fake()->uuid(), 'row_index' => 0,
            'payload_hash' => hash('sha256', fake()->uuid()), 'username' => fake()->unique()->userName(),
            'server_code' => 3, 'old_password' => 'old-secret', 'captcha_api_key' => 'ak_test', 'captcha_api_secret' => 'sk_test',
        ];
    }
}
