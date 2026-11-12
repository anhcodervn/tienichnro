<?php

namespace Database\Factories;

use App\Models\CaptchaTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaptchaTransaction> */
class CaptchaTransactionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'actor_id' => null, 'request_key' => 'test:'.Str::uuid(), 'direction' => 'credit', 'amount' => 10, 'balance_before' => 0, 'balance_after' => 10, 'cost' => 50, 'description' => 'Mua token_captcha'];
    }
}
