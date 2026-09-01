<?php

namespace Database\Factories;

use App\Models\MemberLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MemberLevel>
 */
class MemberLevelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::slug(fake()->unique()->words(2, true)).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->unique()->words(2, true),
            'rank' => fake()->unique()->numberBetween(0, 60000),
            'lifetime_threshold' => fake()->numberBetween(0, 10000000),
            'maintenance_amount' => fake()->numberBetween(0, 1000000),
            'maintenance_days' => 31,
            'default_discount_bps' => fake()->numberBetween(0, 1000),
            'minimum_profit' => fake()->numberBetween(0, 10000),
            'color' => fake()->hexColor(),
            'icon' => 'crown',
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
