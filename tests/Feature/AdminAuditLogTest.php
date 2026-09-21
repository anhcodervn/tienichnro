<?php

use App\Models\AdminAuditLog;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;

test('audit log api only allows administrators', function (): void {
    $this->getJson('/api/admin-api/audit-logs')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/audit-logs')
        ->assertForbidden();

    expect(AdminAuditLog::query()->count())->toBe(0);
});

test('all authenticated admin reads and writes are recorded with sensitive values redacted', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->withHeader('User-Agent', 'AdminAuditLogTest')
        ->patchJson('/api/admin-api/settings/tax', [
            'tax_enabled' => true,
            'tax_calculation_type' => 'revenue',
            'vat_rate' => '1.0000',
            'pit_rate' => '0.5000',
            'password' => 'must-never-be-stored',
            'api_token' => 'token-must-never-be-stored',
            'connection_config' => ['username' => 'provider-user', 'secret' => 'provider-secret'],
        ])
        ->assertOk();

    $writeLog = AdminAuditLog::query()->where('action', 'admin_request_write')->sole();
    $serializedPayload = json_encode($writeLog->new_values);

    expect($writeLog->admin_id)->toBe($admin->id)
        ->and($writeLog->route_name)->toBe('admin-api.settings.update')
        ->and($writeLog->method)->toBe('PATCH')
        ->and($writeLog->path)->toBe('/api/admin-api/settings/tax')
        ->and($writeLog->status_code)->toBe(200)
        ->and($writeLog->request_id)->not->toBeNull()
        ->and($writeLog->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($writeLog->ip)->not->toBeNull()
        ->and($writeLog->user_agent)->toBe('AdminAuditLogTest')
        ->and(data_get($writeLog->new_values, 'input.password'))->toBe('[REDACTED]')
        ->and(data_get($writeLog->new_values, 'input.api_token'))->toBe('[REDACTED]')
        ->and(data_get($writeLog->new_values, 'input.connection_config'))->toBe('[REDACTED]')
        ->and($serializedPayload)->not->toContain('must-never-be-stored')
        ->and($serializedPayload)->not->toContain('provider-secret');

    $this->actingAs($admin)
        ->getJson('/api/admin-api/audit-logs?method=PATCH&action=admin_request_write&search=settings/tax')
        ->assertOk()
        ->assertJsonPath('data.logs.total', 1)
        ->assertJsonPath('data.logs.data.0.id', $writeLog->id)
        ->assertJsonPath('data.logs.data.0.admin.id', $admin->id)
        ->assertJsonPath('data.filter_options.actions.0', 'admin_request_write');

    expect(AdminAuditLog::query()->where('action', 'admin_request_read')->count())->toBe(1);
});

test('failed admin requests are retained for investigation', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/tax', [
            'tax_enabled' => true,
            'tax_calculation_type' => 'profit',
            'vat_rate' => '1.0000',
            'pit_rate' => '0.5000',
        ])
        ->assertUnprocessable();

    $auditLog = AdminAuditLog::query()->where('action', 'admin_request_write')->sole();

    expect($auditLog->status_code)->toBe(422)
        ->and($auditLog->route_name)->toBe('admin-api.settings.update');
});

test('tenant administrators only see audit logs from their own site', function (): void {
    $firstSite = Tenant::factory()->create(['name' => 'Audit Site A']);
    $secondSite = Tenant::factory()->create(['name' => 'Audit Site B']);
    TenantDomain::factory()->for($firstSite)->create(['domain' => 'audit-a.test']);
    TenantDomain::factory()->for($secondSite)->create(['domain' => 'audit-b.test']);
    $firstAdmin = User::factory()->create(['tenant_id' => $firstSite->id, 'role' => 'admin']);
    $secondAdmin = User::factory()->create(['tenant_id' => $secondSite->id, 'role' => 'admin']);

    $firstLog = AdminAuditLog::query()->withoutGlobalScope(TenantScope::class)->create([
        'tenant_id' => $firstSite->id,
        'admin_id' => $firstAdmin->id,
        'action' => 'site_a_action',
        'subject_type' => 'setting',
        'subject_id' => 1,
    ]);
    AdminAuditLog::query()->withoutGlobalScope(TenantScope::class)->create([
        'tenant_id' => $secondSite->id,
        'admin_id' => $secondAdmin->id,
        'action' => 'site_b_action',
        'subject_type' => 'setting',
        'subject_id' => 2,
    ]);

    $this->actingAs($firstAdmin)
        ->getJson('http://audit-a.test/api/admin-api/audit-logs')
        ->assertOk()
        ->assertJsonPath('data.logs.total', 1)
        ->assertJsonPath('data.logs.data.0.id', $firstLog->id)
        ->assertJsonMissing(['action' => 'site_b_action']);

    expect(AdminAuditLog::query()->withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $firstSite->id)
        ->where('action', 'admin_request_read')
        ->count())->toBe(1);
});
