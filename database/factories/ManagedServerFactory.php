<?php

namespace Database\Factories;

use App\Models\ManagedServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagedServer>
 */
class ManagedServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'website', 'server_id' => fake()->unique()->bothify('SV-########'),
            'name' => 'Server '.fake()->word(), 'is_active' => true, 'sort_order' => 0,
        ];
    }
}
