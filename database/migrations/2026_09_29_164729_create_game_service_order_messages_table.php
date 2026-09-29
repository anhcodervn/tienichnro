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
        Schema::create('game_service_order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sender_role', 20);
            $table->text('message');
            $table->timestamps();

            $table->index(['game_service_order_id', 'id'], 'game_service_order_messages_thread_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_service_order_messages');
    }
};
