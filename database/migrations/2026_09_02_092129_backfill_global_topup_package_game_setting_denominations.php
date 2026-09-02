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
        DB::statement(<<<'SQL'
            UPDATE global_topup_package_game_settings
            SET denomination = (
                SELECT global_topup_packages.denomination
                FROM global_topup_packages
                WHERE global_topup_packages.id = global_topup_package_game_settings.global_topup_package_id
            )
            WHERE denomination IS NULL
        SQL);

        if (DB::table('global_topup_package_game_settings')->whereNull('denomination')->exists()) {
            throw new RuntimeException('Khong the chuyen bang thuc nhan Global: con dong khong co menh gia.');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('global_topup_package_game_settings')->update(['denomination' => null]);
    }
};
