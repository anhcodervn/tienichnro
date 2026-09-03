<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::lower(fake()->unique()->bothify('site-####-????')),
            'billing_user_id' => null,
            'status' => 'active',
            'is_main' => false,
            'allow_below_cost' => false,
        ];
    }
}
