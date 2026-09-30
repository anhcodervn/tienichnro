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
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->index(['status', 'completed_at'], 'game_service_orders_status_completed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->dropIndex('game_service_orders_status_completed_at_index');
        });
    }
};
