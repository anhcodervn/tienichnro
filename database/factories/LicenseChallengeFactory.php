<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LicenseChallengeFactory extends Factory
{
    public function definition(): array
    {
        return ['id' => (string) Str::uuid(), 'nonce_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now()->addMinutes(2)];
    }
}
