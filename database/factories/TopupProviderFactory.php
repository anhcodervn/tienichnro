<?php

namespace Database\Factories;

use App\Enums\TopupProviderType;
use App\Models\TopupProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopupProvider>
 */
class TopupProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'type' => TopupProviderType::MerchantPartnerCard,
            'connection_config' => [
                'base_url' => 'https://the9p.com/api/rechargews',
                'partner_id' => fake()->numerify('######'),
                'partner_key' => fake()->sha256(),
                'connect_timeout' => 5,
                'timeout' => 20,
                'max_status_checks' => 20,
            ],
            'payload_field_mapping' => null,
        ];
    }
}
