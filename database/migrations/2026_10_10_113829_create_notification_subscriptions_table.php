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
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('service_package_id')->nullable()->constrained('service_packages')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions');
            $table->string('request_id', 36);
            $table->string('request_hash', 64);
            $table->string('mode', 16);
            $table->string('status', 16)->default('pending');
            $table->string('service_code', 64);
            $table->string('package_name', 120);
            $table->unsignedBigInteger('price');
            $table->string('billing_type', 16);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unsignedInteger('remaining_uses')->nullable();
            $table->string('zalo_id', 128)->nullable();
            $table->json('characters')->nullable();
            $table->json('notification_types')->nullable();
            $table->text('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'request_id']);
            $table->index(['user_id', 'mode', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_subscriptions');
    }
};
