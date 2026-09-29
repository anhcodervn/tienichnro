<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameService>
 */
class GameServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'code' => fake()->unique()->bothify('service-###??'),
            'description' => fake()->sentence(),
            'background_image' => '/storage/uploads/image/game-service-background.webp',
            'payload_fields' => [[
                'key' => 'character_name',
                'label' => 'Tên nhân vật',
                'placeholder' => 'Nhập tên nhân vật',
                'required' => true,
                'regex' => '',
                'type' => 'text',
                'options' => [],
                'min' => null,
                'max' => null,
                'step' => null,
            ]],
            'seo_content' => [],
            'faqs' => [],
            'status' => 'active',
            'sort_order' => 0,
        ];
    }
}
