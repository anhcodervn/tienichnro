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
        Schema::table('game_service_order_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('game_service_order_progress_id')->nullable()->after('sender_role');
            $table->index('game_service_order_progress_id', 'gs_order_message_progress_idx');
            $table->foreign('game_service_order_progress_id', 'gs_order_message_progress_fk')
                ->references('id')
                ->on('game_service_order_progress')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_order_messages', function (Blueprint $table) {
            $table->dropForeign('gs_order_message_progress_fk');
            $table->dropIndex('gs_order_message_progress_idx');
            $table->dropColumn('game_service_order_progress_id');
        });
    }
};
