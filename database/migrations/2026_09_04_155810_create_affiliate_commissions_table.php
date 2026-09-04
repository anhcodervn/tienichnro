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
        Schema::create('affiliate_commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('referrer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referred_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('topup_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commission_type', 20);
            $table->unsignedBigInteger('rate_value');
            $table->unsignedBigInteger('base_amount');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('amount');
            $table->unsignedSmallInteger('holding_days')->default(7);
            $table->string('status', 20)->default('pending');
            $table->boolean('is_flagged')->default(false);
            $table->text('hold_reason')->nullable();
            $table->timestamp('earned_at')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'referrer_id', 'status', 'created_at'], 'affiliate_commissions_referrer_status_index');
            $table->index(['tenant_id', 'status', 'available_at'], 'affiliate_commissions_release_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_commissions');
    }
};
