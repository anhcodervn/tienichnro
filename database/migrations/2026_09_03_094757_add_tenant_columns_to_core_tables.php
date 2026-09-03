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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['tenant_id', 'role']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('tenant_cost_unit_price')->nullable()->after('retail_unit_price');
            $table->unsignedBigInteger('tenant_cost_total')->nullable()->after('tenant_cost_unit_price');
            $table->bigInteger('tenant_profit')->nullable()->after('tenant_cost_total');
            $table->index(['tenant_id', 'created_at']);
        });

        foreach (['wallets', 'wallet_transactions', 'payment_transactions', 'support_conversations', 'support_messages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
                $table->index(['tenant_id', 'created_at'], "{$tableName}_tenant_created_index");
            });
        }

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['support_messages', 'support_conversations', 'payment_transactions', 'wallet_transactions', 'wallets'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex("{$tableName}_tenant_created_index");
                $table->dropConstrainedForeignId('tenant_id');
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('billing_user_id');
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['tenant_cost_unit_price', 'tenant_cost_total', 'tenant_profit']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'role']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
