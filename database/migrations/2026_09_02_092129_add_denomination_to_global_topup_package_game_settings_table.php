<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('denomination')->nullable()->after('game_id');
            $table->index('denomination', 'global_reward_denom_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->dropIndex('global_reward_denom_idx');
            $table->dropColumn('denomination');
        });
    }
};
