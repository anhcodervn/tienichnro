<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'created_at']);
        });

        $mainTenantId = DB::table('tenants')->where('is_main', true)->value('id');

        DB::table('admin_audit_logs')->whereNull('tenant_id')->orderBy('id')
            ->chunkById(500, function ($auditLogs) use ($mainTenantId): void {
                $adminTenantIds = DB::table('users')
                    ->whereIn('id', $auditLogs->pluck('admin_id')->filter())
                    ->pluck('tenant_id', 'id');

                foreach ($auditLogs as $auditLog) {
                    $adminId = (int) ($auditLog->admin_id ?? 0);
                    $tenantId = $adminId > 0 ? $adminTenantIds->get($adminId, $mainTenantId) : $mainTenantId;

                    if ($tenantId !== null) {
                        DB::table('admin_audit_logs')->where('id', $auditLog->id)->update(['tenant_id' => $tenantId]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
