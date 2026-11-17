<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WalletRechargeOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletRechargeOrder>
 */
class WalletRechargeOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'request_id' => (string) Str::uuid(), 'code' => 'TN'.Str::upper(Str::random(24)),
            'provider_code' => 'RCL'.Str::upper(Str::random(20)), 'bank_id' => 25, 'amount' => 10000, 'bank_name' => 'MB',
            'account_name' => 'DEMO', 'account_number' => '123456', 'transfer_content' => 'TEST', 'status' => 'pending', 'api_key' => 'ak_test', 'api_secret' => 'sk_test', 'expires_at' => now()->addHour(),
        ];
    }
}
