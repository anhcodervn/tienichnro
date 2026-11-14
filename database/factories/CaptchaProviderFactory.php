<?php

namespace Database\Factories;

use App\Models\CaptchaProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaptchaProvider> */
class CaptchaProviderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => (string) Str::uuid(), 'name' => 'Provider '.Str::random(10),
            'driver' => 'nro_giaicaptcha', 'endpoint' => 'https://provider.example/solve',
            'priority' => 30, 'timeout' => 10, 'is_enabled' => false, 'api_key' => null,
        ];
    }
}
