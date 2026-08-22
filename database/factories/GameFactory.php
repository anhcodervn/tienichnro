<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
            'short_name' => Str::upper(Str::substr(Str::slug($name, ''), 0, 8)),
            'reward_label' => 'Thực nhận',
            'description' => fake()->sentence(),
            'status' => 'active',
            'sort_order' => fake()->numberBetween(0, 100),
            'metadata' => ['account_label' => 'Tài khoản game'],
            'checkout_fields' => Game::DEFAULT_CHECKOUT_FIELDS,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
