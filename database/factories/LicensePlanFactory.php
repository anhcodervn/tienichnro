<?php

namespace Database\Factories;

use App\Models\LicenseProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicensePlanFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => LicenseProduct::factory(), 'name' => '30 ngày', 'duration_days' => 30, 'price' => 0, 'max_active_devices' => 1, 'transfer_cooldown' => 1800, 'is_active' => true];
    }

    public function lifetime(): static
    {
        return $this->state(fn () => ['duration_days' => null, 'name' => 'Vĩnh viễn']);
    }
}
