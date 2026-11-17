<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_image_capture_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('game_image_accounts')->cascadeOnDelete();
            $table->unsignedInteger('capture_version');
            $table->uuid('client_log_id');
            $table->string('level', 10);
            $table->string('step', 100)->nullable();
            $table->text('message');
            $table->dateTime('occurred_at', 6)->nullable();
            $table->timestamps();
            $table->unique(['account_id', 'capture_version', 'client_log_id'], 'capture_logs_client_unique');
            $table->index(['account_id', 'capture_version', 'id'], 'capture_logs_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_image_capture_logs');
    }
};
