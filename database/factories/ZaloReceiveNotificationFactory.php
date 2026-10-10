<?php

namespace Database\Factories;

use App\Models\ZaloReceiveNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ZaloReceiveNotification>
 */
class ZaloReceiveNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'box_zalo_id' => fake()->numerify('##################'),
            'zalo_id' => fake()->numerify('##################'),
            'char_name' => fake()->userName(),
            'char_server' => null,
            'type_receive' => 'BOSS,SET_ACTIVATION,OTHER',
        ];
    }
}
