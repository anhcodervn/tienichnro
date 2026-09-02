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
        if (DB::table('global_topup_package_game_settings')->whereNull('denomination')->exists()) {
            throw new RuntimeException('Khong the doi khoa bang thuc nhan Global: con dong khong co menh gia.');
        }

        $hasDuplicates = DB::table('global_topup_package_game_settings')
            ->select(['game_id', 'denomination'])
            ->groupBy(['game_id', 'denomination'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException('Khong the doi khoa bang thuc nhan Global: trung game va menh gia.');
        }

        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['global_topup_package_id']
                : 'global_package_game_package_fk');
            $table->dropUnique('global_package_game_unique');
            $table->dropColumn('global_topup_package_id');
            $table->unsignedBigInteger('denomination')->nullable(false)->change();
            $table->unique(['game_id', 'denomination'], 'global_reward_game_denom_unique');
            $table->foreign('denomination', 'global_reward_denom_fk')
                ->references('denomination')
                ->on('global_topup_packages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['denomination']
                : 'global_reward_denom_fk');
            $table->dropUnique('global_reward_game_denom_unique');
            $table->foreignId('global_topup_package_id')->nullable()->after('id');
        });

        DB::statement(<<<'SQL'
            UPDATE global_topup_package_game_settings
            SET global_topup_package_id = (
                SELECT global_topup_packages.id
                FROM global_topup_packages
                WHERE global_topup_packages.denomination = global_topup_package_game_settings.denomination
            )
        SQL);

        if (DB::table('global_topup_package_game_settings')->whereNull('global_topup_package_id')->exists()) {
            throw new RuntimeException('Khong the khoi phuc khoa goi Global cho bang thuc nhan.');
        }

        Schema::table('global_topup_package_game_settings', function (Blueprint $table) {
            $table->foreignId('global_topup_package_id')->nullable(false)->change();
            $table->foreign('global_topup_package_id', 'global_package_game_package_fk')
                ->references('id')
                ->on('global_topup_packages')
                ->cascadeOnDelete();
            $table->unique(['global_topup_package_id', 'game_id'], 'global_package_game_unique');
        });
    }
};
