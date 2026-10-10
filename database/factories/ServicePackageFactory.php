<?php

namespace Database\Factories;

use App\Models\ServiceOffering;
use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePackage>
 */
class ServicePackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_code' => fn (): string => ServiceOffering::factory()->create()->code, 'name' => fake()->words(3, true),
            'description' => fake()->sentence(), 'price' => 50000, 'billing_type' => 'time',
            'duration_days' => 30, 'usage_limit' => null, 'is_active' => true, 'sort_order' => 1,
        ];
    }
}
