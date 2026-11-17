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
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable();
        });
        DB::table('game_password_orders')->update(['queued_at' => DB::raw("CASE WHEN status = 'pending' THEN updated_at ELSE created_at END")]);
        DB::table('game_password_orders')->where('status', 'needs_review')->update(['status' => 'error', 'progress_code' => 'error', 'active_key' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->dropColumn('queued_at');
        });
    }
};
