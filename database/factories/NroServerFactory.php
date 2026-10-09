<?php

namespace Database\Factories;

use App\Models\NroServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NroServer> */
class NroServerFactory extends Factory
{
    public function definition(): array
    {
        return ['server_code' => fake()->unique()->numberBetween(1000, 4294967295), 'name' => fake()->unique()->word(), 'is_active' => true];
    }
}
