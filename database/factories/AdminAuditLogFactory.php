<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdminAuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'admin_id' => User::factory()->state(['role' => 'admin']),
            'action' => 'order_updated',
            'subject_type' => 'order',
            'subject_id' => fake()->numberBetween(1, 1000),
            'old_values' => [],
            'new_values' => [],
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
