<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseEventFactory extends Factory
{
    public function definition(): array
    {
        return ['license_id' => License::factory(), 'event' => 'activated', 'created_at' => now()];
    }
}
