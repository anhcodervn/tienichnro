<?php

namespace Database\Factories;

use App\Models\MemberLevelHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberLevelHistory>
 */
class MemberLevelHistoryFactory extends Factory
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
            'from_level_id' => null,
            'to_level_id' => null,
            'actor_id' => null,
            'type' => 'level_unlocked',
            'reason' => null,
            'lifetime_completed_amount' => 0,
            'metadata' => null,
        ];
    }
}
