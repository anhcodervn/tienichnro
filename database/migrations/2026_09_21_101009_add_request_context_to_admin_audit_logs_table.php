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
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->uuid('request_id')->nullable()->after('admin_id');
            $table->string('route_name')->nullable()->after('action');
            $table->string('method', 10)->nullable()->after('route_name');
            $table->string('path', 1000)->nullable()->after('method');
            $table->unsignedSmallInteger('status_code')->nullable()->after('path');
            $table->unsignedInteger('duration_ms')->nullable()->after('status_code');

            $table->index(['tenant_id', 'admin_id', 'created_at'], 'admin_audit_tenant_admin_created_idx');
            $table->index(['tenant_id', 'route_name', 'created_at'], 'admin_audit_tenant_route_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('admin_audit_tenant_admin_created_idx');
            $table->dropIndex('admin_audit_tenant_route_created_idx');
            $table->dropColumn([
                'request_id',
                'route_name',
                'method',
                'path',
                'status_code',
                'duration_ms',
            ]);
        });
    }
};
