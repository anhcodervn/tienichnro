<?php

namespace Database\Factories;

use App\Models\SeoRedirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoRedirect>
 */
class SeoRedirectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(),
            'to_path' => '/'.fake()->unique()->slug(),
            'status_code' => 301,
        ];
    }
}
