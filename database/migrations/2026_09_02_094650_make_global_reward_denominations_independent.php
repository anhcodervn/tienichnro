<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['denomination']
                : 'global_reward_denom_fk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasIndependentDenominations = DB::table('global_topup_package_game_settings as settings')
            ->leftJoin('global_topup_packages as packages', 'packages.denomination', '=', 'settings.denomination')
            ->whereNull('packages.id')
            ->exists();

        if ($hasIndependentDenominations) {
            throw new RuntimeException('Khong the rollback: bang thuc nhan dang co menh gia doc lap voi goi Global.');
        }

        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->foreign('denomination', 'global_reward_denom_fk')
                ->references('denomination')
                ->on('global_topup_packages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }
};
