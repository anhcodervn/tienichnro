<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GlobalTopupPackageGameSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlobalTopupPackageGameSetting>
 */
class GlobalTopupPackageGameSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'denomination' => 100000,
            'game_id' => Game::factory(),
            'provider_service_code' => fake()->lexify('game-????'),
            'receives' => [[
                'code' => 'ITEM',
                'label' => 'Vật phẩm',
                'base_amount' => 100,
                'reward_x2_amount' => null,
                'reward_x3_amount' => null,
                'first_topup_reward_amount' => null,
            ]],
        ];
    }
}
