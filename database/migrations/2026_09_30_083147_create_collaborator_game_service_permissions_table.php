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
        Schema::create('collaborator_game_service_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'game_service_id']);
            $table->index(['game_service_id', 'user_id'], 'ctv_service_permissions_service_user_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborator_game_service_permissions');
    }
};
