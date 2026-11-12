<?php

namespace Database\Factories;

use App\Models\CaptchaSolve;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaptchaSolve> */
class CaptchaSolveFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'request_id' => (string) Str::uuid(), 'image_hash' => hash('sha256', 'test-captcha'), 'image_path' => 'captcha/'.Str::uuid().'.webp', 'source' => 'demo', 'status' => 'processing'];
    }
}
