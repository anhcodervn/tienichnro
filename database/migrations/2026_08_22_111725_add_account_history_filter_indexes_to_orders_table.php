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
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['user_id', 'payment_status', 'created_at'], 'orders_user_payment_created_index');
            $table->index(['user_id', 'order_status', 'created_at'], 'orders_user_status_created_index');
            $table->index(['user_id', 'game_id', 'created_at'], 'orders_user_game_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_user_payment_created_index');
            $table->dropIndex('orders_user_status_created_index');
            $table->dropIndex('orders_user_game_created_index');
        });
    }
};
