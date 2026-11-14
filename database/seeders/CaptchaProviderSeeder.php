<?php

namespace Database\Seeders;

use App\Models\CaptchaProvider;
use Illuminate\Database\Seeder;

class CaptchaProviderSeeder extends Seeder
{
    public function run(): void
    {
        CaptchaProvider::query()->firstOrCreate(['code' => 'nro-giaicaptcha'], [
            'name' => 'nro.giaicaptcha', 'driver' => 'nro_giaicaptcha',
            'endpoint' => config('captcha.endpoint', 'https://nro.giaicaptcha.vn/solve'),
            'priority' => 10, 'timeout' => 30, 'is_enabled' => true,
        ]);
        CaptchaProvider::query()->firstOrCreate(['code' => 'plusdino'], [
            'name' => 'PlusDino', 'driver' => 'plusdino',
            'endpoint' => 'http://captcha.plusdino.com/SolveCaptcha/API.php',
            'priority' => 20, 'timeout' => 15, 'is_enabled' => false,
        ]);
        CaptchaProvider::query()->firstOrCreate(['code' => 'phamgiang'], [
            'name' => 'api.phamgiang.net', 'driver' => 'phamgiang',
            'endpoint' => 'https://api.phamgiang.net/captcha/nro',
            'priority' => 30, 'timeout' => 15, 'is_enabled' => false,
        ]);
    }
}
