<?php

namespace Database\Factories;

use App\Models\GameImageAccount;
use App\Models\GameImageCaptureLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameImageCaptureLog> */
class GameImageCaptureLogFactory extends Factory
{
    public function definition(): array
    {
        return ['account_id' => GameImageAccount::factory(), 'capture_version' => 1,
            'client_log_id' => fake()->uuid(), 'level' => 'info', 'step' => 'login',
            'message' => fake()->sentence(), 'occurred_at' => now()];
    }
}
