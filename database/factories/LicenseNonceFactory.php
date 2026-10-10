<?php

namespace Database\Factories;

use App\Models\LicenseDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseNonceFactory extends Factory
{
    public function definition(): array
    {
        return ['device_id' => LicenseDevice::factory(), 'nonce_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now()->addMinutes(3)];
    }
}
