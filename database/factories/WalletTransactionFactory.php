<?php

namespace Database\Factories;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory()->state(['balance' => 10000]), 'actor_id' => null, 'direction' => 'credit',
            'amount' => 10000, 'balance_before' => 0, 'balance_after' => 10000,
            'idempotency_key' => fake()->uuid(), 'description' => fake()->sentence(),
        ];
    }
}
