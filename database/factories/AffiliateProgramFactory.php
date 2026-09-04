<?php

namespace Database\Factories;

use App\Models\AffiliateProgram;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateProgram>
 */
class AffiliateProgramFactory extends Factory
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
            'is_enabled' => true,
            'minimum_withdrawal' => 100000,
        ];
    }
}
