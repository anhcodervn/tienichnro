<?php

namespace Database\Factories;

use App\Models\CaptchaApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaptchaApiKey> */
class CaptchaApiKeyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'token_hash' => hash('sha256', Str::random(64)), 'prefix' => 'nrocap_test', 'last_used_at' => null];
    }
}
