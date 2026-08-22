<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class TopupPackageFactory extends Factory
{
    public function definition(): array
    {
        $carot = fake()->randomElement([100, 500, 1000, 2000]);

        return [
            'game_id' => Game::factory(),
            'game_server_id' => null,
            'provider_id' => null,
            'provider_service_code' => null,
            'name' => number_format($carot, 0, ',', '.').' Carot',
            'carot_amount' => $carot,
            'reward_x2_amount' => $carot * 2,
            'reward_x3_amount' => $carot * 3,
            'first_topup_reward_amount' => $carot * 2,
            'provider_price' => $carot * 850,
            'price' => $carot * 900,
            'original_price' => $carot * 1000,
            'min_quantity' => 1,
            'max_quantity' => 10,
            'status' => 'active',
            'sort_order' => $carot,
            'metadata' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
