<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseDeviceFactory extends Factory
{
    public function definition(): array
    {
        return ['license_id' => License::factory(), 'device_uuid' => fake()->uuid(), 'device_name' => 'TEST-PC', 'public_key' => 'test-key', 'first_activated_at' => now(), 'last_seen_at' => now(), 'status' => 'active'];
    }
}
