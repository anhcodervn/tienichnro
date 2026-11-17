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
        Schema::create('game_password_pruned_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->unique(['user_id', 'request_id'], 'password_pruned_request_unique');
        });
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->index(['status', 'completed_at'], 'password_orders_cleanup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_password_orders', function (Blueprint $table): void {
            $table->dropIndex('password_orders_cleanup_index');
        });
        Schema::dropIfExists('game_password_pruned_requests');
    }
};
