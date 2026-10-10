<?php

namespace Database\Factories;

use App\Models\ServiceOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOffering>
 */
class ServiceOfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'url' => '/tin-tuc',
            'icon_type' => 'icon',
            'icon' => 'bx-store',
            'image_url' => null,
            'is_enabled' => true,
            'maintenance_message' => 'Dịch vụ đang bảo trì. Vui lòng quay lại sau.',
            'sort_order' => 1,
        ];
    }
}
