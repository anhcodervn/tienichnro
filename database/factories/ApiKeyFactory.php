<?php

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'key_type' => 'topup',
            'name' => fake()->words(2, true),
            'api_key' => 'nck_'.Str::lower(Str::random(40)),
            'api_secret_hash' => Hash::make(Str::random(68)),
            'permissions' => ['balance:read', 'tasks:create', 'tasks:read'],
            'ip_whitelist' => null,
            'status' => 'active',
            'expired_at' => now()->addYear(),
        ];
    }
}
