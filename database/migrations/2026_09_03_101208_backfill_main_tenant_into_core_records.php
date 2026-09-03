<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $mainTenantId = (int) DB::table('tenants')->where('is_main', true)->value('id');

        foreach (['users', 'orders', 'wallets', 'wallet_transactions', 'payment_transactions', 'support_conversations', 'support_messages'] as $tableName) {
            DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $mainTenantId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tenant ownership is intentionally retained when rolling back this data migration.
    }
};
