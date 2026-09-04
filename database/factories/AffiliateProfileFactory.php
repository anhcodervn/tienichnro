<?php

namespace Database\Factories;

use App\Models\AffiliateProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateProfile>
 */
class AffiliateProfileFactory extends Factory
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
            'status' => 'active',
            'bank_name' => 'Vietcombank',
            'bank_account_name' => fake()->name(),
            'bank_account_number' => fake()->numerify('##########'),
        ];
    }
}
