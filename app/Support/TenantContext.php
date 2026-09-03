<?php

namespace App\Support;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\Schema;

class TenantContext
{
    private ?Tenant $tenant = null;

    private ?bool $schemaReady = null;

    /** @var array<string, bool> */
    private array $tenantColumns = [];

    private bool $mainTenantResolved = false;

    private ?Tenant $mainTenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function idOrMain(): ?int
    {
        if ($this->tenant instanceof Tenant) {
            return $this->tenant->id;
        }

        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return $this->mainTenant()?->id;
    }

    public function mainTenant(): ?Tenant
    {
        if ($this->mainTenantResolved) {
            return $this->mainTenant;
        }

        $this->mainTenantResolved = true;

        if (! Schema::hasTable('tenants')) {
            return null;
        }

        return $this->mainTenant = Tenant::query()->where('is_main', true)->first();
    }

    public function hasTenantColumn(string $tableName): bool
    {
        return $this->tenantColumns[$tableName] ??= Schema::hasColumn($tableName, 'tenant_id');
    }

    public function isActive(): bool
    {
        return (bool) config('tenancy.enabled', false) && $this->isSchemaReady();
    }

    public function isSchemaReady(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        if (! Schema::hasTable('tenants') || ! Schema::hasTable('tenant_domains')) {
            return $this->schemaReady = false;
        }

        foreach (['users', 'orders', 'wallets', 'wallet_transactions', 'payment_transactions', 'support_conversations', 'support_messages'] as $tableName) {
            if (! $this->hasTenantColumn($tableName)) {
                return $this->schemaReady = false;
            }
        }

        return $this->schemaReady = Schema::hasColumns('orders', [
            'billing_user_id',
            'tenant_cost_unit_price',
            'tenant_cost_total',
            'tenant_profit',
        ]);
    }

    public function isMain(): bool
    {
        return ! $this->isActive() || $this->tenant?->is_main === true;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}
