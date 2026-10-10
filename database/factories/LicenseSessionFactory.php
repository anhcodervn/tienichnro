<?php

namespace Database\Factories;

use App\Models\LicenseDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LicenseSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'device_id' => LicenseDevice::factory(), 'license_id' => fn (array $attributes) => LicenseDevice::query()->findOrFail($attributes['device_id'])->license_id, 'token_hash' => hash('sha256', random_bytes(32)), 'generation' => 1, 'status' => 'active', 'started_at' => now(), 'last_heartbeat_at' => now(), 'lease_expires_at' => now()->addMinute()];
    }
}
