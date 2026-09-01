<?php

namespace Database\Factories;

use App\Models\MemberLevelOrderCredit;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberLevelOrderCredit>
 */
class MemberLevelOrderCreditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'amount' => fake()->numberBetween(10000, 1000000),
            'occurred_at' => now(),
            'reversed_at' => null,
        ];
    }
}
