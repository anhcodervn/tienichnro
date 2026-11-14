<?php

use App\Models\CaptchaProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        CaptchaProvider::query()->firstOrCreate(['code' => 'phamgiang'], [
            'name' => 'api.phamgiang.net', 'driver' => 'phamgiang',
            'endpoint' => 'https://api.phamgiang.net/captcha/nro',
            'priority' => 30, 'timeout' => 15, 'is_enabled' => false,
        ]);
    }

    /** Provider configuration may have been edited; schema rollback owns its removal. */
    public function down(): void {}
};
