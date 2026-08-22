<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('normalized_email')->index();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('game_server_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topup_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('game_account');
            $table->string('game_character')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('package_name');
            $table->unsignedBigInteger('denomination')->nullable();
            $table->unsignedBigInteger('carot_amount')->nullable();
            $table->decimal('unit_price', 20, 2);
            $table->decimal('subtotal', 20, 2);
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2);
            $table->string('payment_method', 30)->index();
            $table->string('payment_status', 20)->default('pending')->index();
            $table->string('order_status', 20)->default('pending')->index();
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('customer_ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['game_id', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index(['order_status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
