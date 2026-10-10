<?php

namespace Database\Seeders;

use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use Illuminate\Database\Seeder;

class LicensePlanSeeder extends Seeder
{
    public function run(): void
    {
        LicenseProduct::query()->each(function (LicenseProduct $product): void {
            foreach ([1, 7, 30, 90, 365, null] as $days) {
                LicensePlan::query()->firstOrCreate(['product_id' => $product->id, 'name' => $days === null ? 'Vĩnh viễn' : $days.' ngày'], ['duration_days' => $days, 'price' => 0, 'max_active_devices' => 1, 'transfer_cooldown' => $product->transfer_cooldown, 'is_active' => true]);
            }
        });
    }
}
