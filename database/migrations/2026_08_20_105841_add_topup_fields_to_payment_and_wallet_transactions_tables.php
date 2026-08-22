<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('provider_transaction_id')->nullable()->after('transaction_code')->unique();
        });

        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->uuid('idempotency_key')->nullable()->after('reference_id')->unique();
            $table->json('metadata')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'metadata']);
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropForeign(['order_id']);
            $table->dropUnique(['provider_transaction_id']);
            $table->dropColumn(['order_id', 'provider_transaction_id']);
        });
    }
};
