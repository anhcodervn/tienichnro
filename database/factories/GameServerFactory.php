<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameServerFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 9999);

        return [
            'game_id' => Game::factory(),
            'name' => 'Máy chủ '.$number,
            'code' => 'S'.$number,
            'status' => 'active',
            'sort_order' => $number,
            'metadata' => [],
        ];
    }
}
