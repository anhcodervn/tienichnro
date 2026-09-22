<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameSeoSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSeoSetting>
 */
class GameSeoSettingFactory extends Factory
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
            'meta_title' => fake()->sentence(6),
            'meta_description' => fake()->sentence(14),
            'meta_keywords' => implode(', ', fake()->words(4)),
            'h1' => fake()->sentence(5),
            'article_title' => fake()->sentence(7),
            'content' => [['type' => 'paragraph', 'children' => [['text' => fake()->paragraph()]]]],
            'robots' => 'index,follow',
            'faqs' => [],
            'is_published' => true,
            'breadcrumb_schema' => true,
            'webpage_schema' => true,
        ];
    }
}
