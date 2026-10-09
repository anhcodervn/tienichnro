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
        Schema::table('code_notifies', function (Blueprint $table): void {
            $table->string('system_key', 64)->nullable()->unique();
            $table->softDeletes();
        });
        foreach (['BOSS', 'SET_ACTIVATION', 'MAINTENANCE', 'CRYSTAL_UPGRADE', 'ITEM_UPGRADE', 'GOD_ITEM', 'PERMANENT_ITEM', 'OTHER'] as $code) {
            DB::table('code_notifies')->where('code', $code)->update(['system_key' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('code_notifies', function (Blueprint $table): void {
            $table->dropUnique(['system_key']);
            $table->dropColumn(['system_key', 'deleted_at']);
        });
    }
};
