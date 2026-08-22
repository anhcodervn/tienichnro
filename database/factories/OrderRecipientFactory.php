<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRecipient>
 */
class OrderRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'position' => 1,
            'recipient_data' => ['game_account' => fake()->userName()],
            'quantity' => 1,
            'status' => 'pending',
        ];
    }
}
