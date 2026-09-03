<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\User;
use App\Utils\Setting;
use App\Utils\Site;

test('domain resolves the current site and isolates settings with global fallback', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Đại lý A']);
    TenantDomain::factory()->for($tenant)->create(['domain' => 'dailycarot.test']);

    Site::for($tenant, function (): void {
        Setting::put('site_name', 'Daily Carot');
        expect(Site::mySite()?->name)->toBe('Đại lý A')
            ->and(Site::isChild())->toBeTrue()
            ->and(Setting::get('site_name'))->toBe('Daily Carot');
    });

    expect(TenantSetting::query()->where('tenant_id', $tenant->id)->where('key', 'site_name')->value('value'))->toBe('Daily Carot');

    $this->get('http://dailycarot.test/')
        ->assertSuccessful()
        ->assertSee('Daily Carot');
});

test('unknown and suspended domains show a neutral unavailable page without exposing the main site', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    Site::for($main, fn () => Setting::put('site_name', 'PRIVATE MAIN SITE BRAND'));

    $this->get('http://unknown.test/')
        ->assertNotFound()
        ->assertSee('Tên miền đã được trỏ thành công nhưng website chưa được kích hoạt')
        ->assertSee('Nếu bạn là chủ website, hãy liên hệ quản trị viên để kiểm tra tên miền và hoàn tất kích hoạt.')
        ->assertSee('unknown.test')
        ->assertSee('data-site-unavailable="unregistered"', false)
        ->assertSee('bg-slate-50', false)
        ->assertSee('border-amber-200', false)
        ->assertDontSee('bg-slate-950', false)
        ->assertSee('data-status-icon="warning"', false)
        ->assertSee('data-status-icon="refresh"', false)
        ->assertDontSee('/assets/icon/boxicons', false)
        ->assertDontSee('bx bx-', false)
        ->assertDontSee('PRIVATE MAIN SITE BRAND');

    $tenant = Tenant::factory()->create(['name' => 'Daily Carot', 'status' => 'suspended']);
    TenantDomain::factory()->for($tenant)->create(['domain' => 'suspended.test']);

    $this->get('http://suspended.test/')
        ->assertServiceUnavailable()
        ->assertSee('Website đang tạm ngưng')
        ->assertSee('Daily Carot')
        ->assertSee('data-site-unavailable="suspended"', false)
        ->assertDontSee('PRIVATE MAIN SITE BRAND');
});

test('an unverified mapped domain shows activation status and returns neutral JSON for api requests', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Waiting Carot']);
    TenantDomain::factory()->for($tenant)->create([
        'domain' => 'waiting.test',
        'is_verified' => false,
    ]);

    $this->get('http://waiting.test/')
        ->assertServiceUnavailable()
        ->assertSee('Tên miền đã được trỏ thành công nhưng website chưa được kích hoạt')
        ->assertSee('Waiting Carot')
        ->assertSee('data-site-unavailable="unverified"', false)
        ->assertDontSee('NapCarot');

    $this->getJson('http://waiting.test/api/user')
        ->assertServiceUnavailable()
        ->assertJsonPath('status', false)
        ->assertJsonPath('data.site_unavailable', true)
        ->assertJsonPath('data.state', 'unverified')
        ->assertJsonPath('data.domain', 'waiting.test')
        ->assertJsonMissingPath('data.site');
});

test('napcarot.com is always registered as the primary main domain', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();

    expect(TenantDomain::query()
        ->where('tenant_id', $main->id)
        ->where('domain', 'napcarot.com')
        ->where('is_primary', true)
        ->where('is_verified', true)
        ->exists())->toBeTrue();
    expect(TenantDomain::query()
        ->where('tenant_id', $main->id)
        ->where('domain', 'www.napcarot.com')
        ->where('is_verified', true)
        ->exists())->toBeTrue();

    $this->get('http://napcarot.com/')->assertSuccessful();
    $this->get('http://www.napcarot.com/')->assertSuccessful();
});

test('a verified child domain supports sanctum session authentication without static configuration', function (): void {
    config()->set('sanctum.stateful', ['napcarot.com']);

    $tenant = Tenant::factory()->create(['name' => 'Child Sanctum']);
    TenantDomain::factory()->for($tenant)->create(['domain' => 'child-sanctum.test']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $headers = [
        'Accept' => 'application/json',
        'Origin' => 'http://child-sanctum.test',
        'Referer' => 'http://child-sanctum.test/',
    ];

    $this->withHeaders($headers)
        ->postJson('http://child-sanctum.test/dang-nhap', [
            'login' => $user->username,
            'password' => 'password',
        ])
        ->assertSuccessful();

    $this->withHeaders($headers)
        ->getJson('http://child-sanctum.test/api/user')
        ->assertSuccessful()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('site.id', $tenant->id);
});

test('legacy mode keeps the running main site and platform admin routes available', function (): void {
    config()->set('tenancy.enabled', false);
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);

    $this->get('http://unregistered-host.test/')->assertSuccessful();
    $this->actingAs($admin)
        ->getJson('http://unregistered-host.test/api/admin-api/games')
        ->assertSuccessful();
    $this->actingAs($admin)
        ->getJson('http://unregistered-host.test/api/admin-api/tenants')
        ->assertServiceUnavailable();
});

test('tenancy readiness command validates the migrated database', function (): void {
    $this->artisan('tenancy:check')->assertSuccessful();
});

test('strict tenancy readiness requires the runtime feature flag', function (): void {
    config()->set('tenancy.enabled', false);

    $this->artisan('tenancy:check', ['--strict' => true])->assertFailed();
});
