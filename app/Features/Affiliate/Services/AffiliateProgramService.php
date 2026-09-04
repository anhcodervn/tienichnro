<?php

namespace App\Features\Affiliate\Services;

use App\Models\AffiliateProgram;
use App\Support\TenantContext;

class AffiliateProgramService
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function current(): AffiliateProgram
    {
        $tenantId = $this->tenantId();

        return AffiliateProgram::query()->firstOrNew(['tenant_id' => $tenantId]);
    }

    public function enabled(): ?AffiliateProgram
    {
        return AffiliateProgram::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_enabled', true)
            ->first();
    }

    public function tenantId(): int
    {
        $tenantId = $this->tenantContext->idOrMain();

        abort_if($tenantId === null, 503, 'Website chưa sẵn sàng cho chương trình cộng tác viên.');

        return $tenantId;
    }
}
