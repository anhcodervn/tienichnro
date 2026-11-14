<?php

use App\Models\CaptchaProvider;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $provider = CaptchaProvider::query()->where('is_enabled', true)->orderBy('priority')->orderBy('id')->first();
            Setting::query()->firstOrCreate(['key' => 'captcha.primary_provider_id'], ['value' => $provider ? (string) $provider->id : '', 'type' => 'string']);
            $selectedId = (int) Setting::query()->where('key', 'captcha.primary_provider_id')->value('value');
            CaptchaProvider::query()->where('id', '!=', $selectedId)->update(['is_enabled' => false]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::query()->where('key', 'captcha.primary_provider_id')->delete();
    }
};
