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
            $table->foreignId('collaborator_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['collaborator_id', 'status', 'created_at'], 'game_service_orders_collaborator_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_service_orders', function (Blueprint $table) {
            $table->dropIndex('game_service_orders_collaborator_status_index');
            $table->dropConstrainedForeignId('collaborator_id');
        });
    }
};
