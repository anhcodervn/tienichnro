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
        Schema::create('wallet_recharge_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('request_id');
            $table->string('code', 40)->unique();
            $table->string('provider_code', 100)->nullable()->unique();
            $table->unsignedBigInteger('bank_id');
            $table->unsignedBigInteger('amount');
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 100)->nullable();
            $table->string('account_name', 200)->nullable();
            $table->string('transfer_content', 200)->nullable();
            $table->string('status', 30)->default('creating');
            $table->string('bank_transaction_id', 100)->nullable()->unique();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions');
            $table->text('api_key');
            $table->text('api_secret');
            $table->string('last_error', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['status', 'last_checked_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_recharge_orders');
    }
};
