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
        Schema::create('notification_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('notification_subscriptions');
            $table->string('event_key', 64);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->boolean('quota_reserved')->default(false);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'event_key']);
            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_webhook_deliveries');
    }
};
