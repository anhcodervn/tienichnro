<?php

namespace Database\Factories;

use App\Models\CaptchaProvider;
use App\Models\CaptchaProviderAttempt;
use App\Models\CaptchaSolve;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CaptchaProviderAttempt> */
class CaptchaProviderAttemptFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'captcha_solve_id' => CaptchaSolve::factory(), 'captcha_provider_id' => CaptchaProvider::factory(),
            'provider_name' => 'Test provider', 'driver' => 'nro_giaicaptcha', 'status' => 'processing',
        ];
    }
}
