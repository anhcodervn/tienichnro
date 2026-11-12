<?php

namespace Database\Factories;

use App\Models\CaptchaWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CaptchaWallet> */
class CaptchaWalletFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'balance' => 0, 'reserved' => 0];
    }
}
