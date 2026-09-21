<?php

namespace Database\Factories;

use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\TopupProviderPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopupProviderPrice>
 */
class TopupProviderPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'topup_provider_id' => TopupProvider::factory(),
            'topup_package_id' => TopupPackage::factory(),
            'global_topup_package_id' => null,
            'price' => fake()->numberBetween(1_000, 1_000_000),
        ];
    }
}
