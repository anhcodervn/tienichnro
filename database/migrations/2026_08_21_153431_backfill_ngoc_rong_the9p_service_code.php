<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gameId = DB::table('games')->where('slug', 'ngoc-rong-online')->value('id');
        $providerId = DB::table('topup_providers')->where('slug', 'the9p')->value('id');

        if ($gameId === null || $providerId === null) {
            return;
        }

        DB::table('topup_packages')
            ->where('game_id', $gameId)
            ->where('provider_id', $providerId)
            ->whereNull('provider_service_code')
            ->update(['provider_service_code' => 'nr']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Existing administrator values must not be removed during rollback.
    }
};
