<?php

namespace Database\Factories;

use App\Models\AffiliateWithdrawal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateWithdrawal>
 */
class AffiliateWithdrawalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'amount' => 100000,
            'wallet_type' => AffiliateWithdrawal::WALLET_AFFILIATE,
            'status' => AffiliateWithdrawal::STATUS_REQUESTED,
            'bank_name' => 'Vietcombank',
            'bank_account_name' => fake()->name(),
            'bank_account_number' => fake()->numerify('##########'),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
