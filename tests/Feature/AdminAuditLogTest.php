<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('retired admin audit log api is unavailable', function (): void {
    $this->getJson('/api/admin-api/audit-logs')->assertNotFound();
    $this->actingAs(User::factory()->create())->getJson('/api/admin-api/audit-logs')->assertNotFound();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/audit-logs')->assertNotFound();
});

test('admin reads writes and validation failures do not access audit storage', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $auditQueries = [];
    DB::listen(function ($query) use (&$auditQueries): void {
        if (str_contains($query->sql, 'admin_audit_logs')) {
            $auditQueries[] = $query->sql;
        }
    });

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->getJson('/api/admin-api/users')->assertOk();
    $this->patchJson('/api/admin-api/settings/custom-code', [
        'custom_css' => '.audit-removed { color: green; }',
        'custom_css_enabled' => true,
    ])->assertOk()->assertJsonPath('data.settings.custom_css_enabled', true);
    $this->getJson('/api/admin-api/settings/custom-code')->assertOk()
        ->assertJsonPath('data.settings.custom_css', '.audit-removed { color: green; }');
    $this->patchJson('/api/admin-api/settings/custom-code', ['custom_js_enabled' => 'invalid'])
        ->assertUnprocessable();

    expect($auditQueries)->toBeEmpty();
});

test('admin settings work without the legacy audit table', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    Schema::dropIfExists('admin_audit_logs');

    $this->actingAs($admin)->patchJson('/api/admin-api/settings/custom-code', [
        'custom_js' => 'window.auditRemoved = true;',
        'custom_js_enabled' => true,
    ])->assertOk()->assertJsonPath('data.settings.custom_js', 'window.auditRemoved = true;');
    $this->getJson('/api/admin-api/users')->assertOk();
});
