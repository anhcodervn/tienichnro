<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class CaptchaExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(['key' => 'captcha.exchange_rates'], [
            'type' => 'json',
            'value' => json_encode([
                ['min_tokens' => 1, 'unit_price' => 5],
                ['min_tokens' => 1000, 'unit_price' => 3],
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
