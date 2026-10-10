<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseProductFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->words(2, true), 'product_code' => strtoupper(fake()->unique()->bothify('TOOL_??????')), 'minimum_version' => '1.0.0', 'is_active' => true, 'heartbeat_interval' => 20, 'lease_duration' => 60, 'transfer_cooldown' => 1800, 'max_active_devices' => 1, 'offline_grace' => 0];
    }
}
