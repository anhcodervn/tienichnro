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
        Schema::table('config_recharge', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->index(['tenant_id', 'is_active'], 'config_recharge_tenant_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('config_recharge', function (Blueprint $table) {
            $table->dropIndex('config_recharge_tenant_active_index');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
