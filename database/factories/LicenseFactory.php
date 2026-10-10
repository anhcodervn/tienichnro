<?php

namespace Database\Factories;

use App\Features\License\Services\LicenseSessionService;
use App\Models\LicensePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LicenseFactory extends Factory
{
    public function definition(): array
    {
        return ['plan_id' => LicensePlan::factory(), 'product_id' => fn (array $attributes) => LicensePlan::query()->findOrFail($attributes['plan_id'])->product_id, 'key_hash' => LicenseSessionService::hashKey(bin2hex(random_bytes(16))), 'key_prefix' => 'TEST-TEST', 'status' => 'unused', 'duration_days' => 30, 'generation' => 0, 'max_active_devices' => 1, 'transfer_cooldown' => 1800];
    }
}
