<?php

use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

test('the single website has no tenancy or support dependencies and keeps admin authorization', function (): void {
    foreach (['tenants', 'tenant_domains', 'tenant_settings', 'support_conversations', 'support_messages'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    foreach (['users', 'admin_audit_logs'] as $table) {
        expect(Schema::hasColumn($table, 'tenant_id'))->toBeFalse();
    }
    foreach (Route::getRoutes() as $route) {
        expect($route->uri())->not->toContain('support', 'tenants', 'sites');
        expect($route->gatherMiddleware())->not->toContain('tenancy.active');
    }
    $user = User::factory()->create();
    expect(method_exists($user, 'tenant'))->toBeFalse();
    expect(method_exists($user, 'supportConversation'))->toBeFalse();
    $this->getJson('/api/admin-api/nro/bosses')->assertUnauthorized();
    $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonMissingPath('site')->assertJsonMissingPath('capabilities.multi_site');
    $this->getJson('/api/admin-api/nro/bosses')->assertForbidden();
    $this->getJson('/api/admin-api/users')->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->getJson('/api/admin-api/nro/bosses')->assertOk();
    $this->getJson('/api/admin-api/users')->assertOk();
    $this->getJson('/api/admin-api/settings/support-channels')->assertNotFound();
    $this->getJson('/api/admin-api/support/conversations')->assertNotFound();
    $this->get('/thong-bao-game')->assertOk();
    app(SettingStore::class)->putString('single_site_test', 'preserved');
    expect(app(SettingStore::class)->getString('single_site_test'))->toBe('preserved');
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
});

test('retirement refuses support data before changing accounts or tables', function (): void {
    Schema::create('support_messages', function (Blueprint $table): void {
        $table->id();
    });
    DB::table('support_messages')->insert(['id' => 1]);
    $migration = require database_path('migrations/2026_10_08_104336_remove_tenancy_and_support.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Cannot remove support_messages');
    $this->assertDatabaseHas('support_messages', ['id' => 1]);
});
