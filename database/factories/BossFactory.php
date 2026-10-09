<?php

namespace Database\Factories;

use App\Models\Boss;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Boss> */
class BossFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('BOSS_########'), 'name' => fake()->unique()->name(), 'respawn_seconds' => 1800, 'is_active' => true];
    }
}
