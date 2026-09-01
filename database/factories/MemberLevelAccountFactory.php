<?php

namespace Database\Factories;

use App\Models\MemberLevelAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberLevelAccount>
 */
class MemberLevelAccountFactory extends Factory
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
            'earned_level_id' => null,
            'manual_level_id' => null,
            'lifetime_completed_amount' => 0,
            'last_qualified_order_at' => null,
            'manual_level_expires_at' => null,
        ];
    }
}
