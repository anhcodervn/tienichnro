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
        Schema::create('game_service_order_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->index();
            $table->text('description');
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['game_service_order_id', 'created_at'], 'gs_order_progress_order_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_service_order_progress');
    }
};
