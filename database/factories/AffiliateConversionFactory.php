<?php

namespace Database\Factories;

use App\Models\AffiliateConversion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AffiliateConversion>
 */
class AffiliateConversionFactory extends Factory
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
            'amount' => 10000,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'completed',
        ];
    }
}
