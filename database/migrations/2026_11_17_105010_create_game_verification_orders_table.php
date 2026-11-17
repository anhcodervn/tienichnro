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
        Schema::create('game_verification_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->unsignedSmallInteger('row_index');
            $table->string('payload_hash', 64);
            $table->string('username', 100);
            $table->unsignedInteger('server_code');
            $table->text('old_password')->nullable();
            $table->text('captcha_api_key')->nullable();
            $table->text('captcha_api_secret')->nullable();
            $table->text('sms_syntax')->nullable();
            $table->string('active_key', 64)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->string('progress_code', 40)->default('queued');
            $table->string('error_code', 60)->nullable();
            $table->json('progress_logs')->nullable();
            $table->unsignedInteger('attempt_version')->default(1);
            $table->unsignedBigInteger('charged_amount')->default(0);
            $table->foreignId('charge_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unique(['user_id', 'request_id', 'row_index'], 'verification_request_unique');
            $table->index(['user_id', 'status', 'id'], 'verification_history_index');
            $table->index(['status', 'completed_at'], 'verification_cleanup_index');
            $table->timestamps();
        });
        Schema::create('game_verification_pruned_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->unique(['user_id', 'request_id'], 'verification_pruned_request_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_verification_pruned_requests');
        Schema::dropIfExists('game_verification_orders');
    }
};
