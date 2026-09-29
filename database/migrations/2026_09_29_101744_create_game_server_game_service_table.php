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
        Schema::create('game_server_game_service', function (Blueprint $table) {
            $table->foreignId('game_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_server_id')->constrained()->cascadeOnDelete();

            $table->primary(['game_service_id', 'game_server_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_server_game_service');
    }
};
