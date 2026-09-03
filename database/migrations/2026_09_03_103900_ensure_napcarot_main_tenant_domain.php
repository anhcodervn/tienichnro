<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('tenant_domains')) {
            return;
        }

        $mainTenantId = DB::table('tenants')->where('is_main', true)->value('id');
        $mainDomain = strtolower(trim((string) config('tenancy.main_domain', 'napcarot.com')));
        $mainDomains = collect([$mainDomain, ...config('tenancy.main_aliases', [])])
            ->map(fn (mixed $domain): string => strtolower(trim((string) $domain)))
            ->filter()
            ->unique()
            ->values();

        if ($mainTenantId === null || $mainDomain === '') {
            throw new RuntimeException('Khong tim thay website me hoac TENANCY_MAIN_DOMAIN khong hop le.');
        }

        foreach ($mainDomains as $domain) {
            $domainOwnerId = DB::table('tenant_domains')->where('domain', $domain)->value('tenant_id');

            if ($domainOwnerId !== null && (int) $domainOwnerId !== (int) $mainTenantId) {
                throw new RuntimeException("Domain {$domain} dang thuoc mot website khac.");
            }
        }

        DB::transaction(function () use ($mainTenantId, $mainDomain, $mainDomains): void {
            DB::table('tenant_domains')->where('tenant_id', $mainTenantId)->update(['is_primary' => false]);

            foreach ($mainDomains as $domain) {
                DB::table('tenant_domains')->updateOrInsert(
                    ['domain' => $domain],
                    [
                        'tenant_id' => $mainTenantId,
                        'is_primary' => $domain === $mainDomain,
                        'is_verified' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The main production domain is intentionally retained on rollback.
    }
};
