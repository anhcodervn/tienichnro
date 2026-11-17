<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_password_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->unsignedSmallInteger('row_index');
            $table->string('payload_hash', 64);
            $table->string('username', 100);
            $table->unsignedInteger('server_code');
            $table->text('old_password')->nullable();
            $table->text('new_password')->nullable();
            $table->string('active_key', 64)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('progress_code', 40)->default('queued');
            $table->string('error_code', 60)->nullable();
            $table->unsignedBigInteger('charged_amount')->default(0);
            $table->foreignId('charge_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('refund_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('change_submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id', 'row_index'], 'password_order_request_unique');
            $table->index(['user_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_password_orders');
    }
};
