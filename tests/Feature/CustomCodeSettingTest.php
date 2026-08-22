<?php

use App\Models\AdminAuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;

test('only admins can read and update custom code settings', function (): void {
    $this->getJson('/api/admin-api/settings/custom-code')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/custom-code', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/custom-code')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/custom-code', [])->assertForbidden();
});

test('admin can store and read exact custom css and javascript', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $customCss = "\n.site-header {\n    color: #0f172a;\n}\n";
    $customJs = "\nwindow.siteCustomReady = true;\n";

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/custom-code')
        ->assertOk()
        ->assertJsonPath('data.settings.custom_css', '')
        ->assertJsonPath('data.settings.custom_css_enabled', false)
        ->assertJsonPath('data.settings.custom_js', '')
        ->assertJsonPath('data.settings.custom_js_enabled', false);

    $this->withHeader('User-Agent', 'CustomCodeSettingTest')
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->actingAs($admin)
        ->patchJson('/api/admin-api/settings/custom-code', [
            'custom_css' => $customCss,
            'custom_css_enabled' => true,
            'custom_js' => $customJs,
            'custom_js_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.custom_css', $customCss)
        ->assertJsonPath('data.settings.custom_css_enabled', true)
        ->assertJsonPath('data.settings.custom_js', $customJs)
        ->assertJsonPath('data.settings.custom_js_enabled', true);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/custom-code')
        ->assertOk()
        ->assertJsonPath('data.settings.custom_css', $customCss)
        ->assertJsonPath('data.settings.custom_js', $customJs);

    expect(Setting::query()->where('key', 'custom_css')->value('value'))->toBe($customCss)
        ->and(Setting::query()->where('key', 'custom_css_enabled')->value('type'))->toBe('boolean')
        ->and(Setting::query()->where('key', 'custom_js')->value('value'))->toBe($customJs);

    $audit = AdminAuditLog::query()->where('action', 'custom_code_updated')->firstOrFail();

    expect($audit->admin_id)->toBe($admin->id)
        ->and($audit->ip)->toBe('203.0.113.10')
        ->and($audit->user_agent)->toBe('CustomCodeSettingTest')
        ->and($audit->new_values['custom_css_sha256'])->toBe(hash('sha256', $customCss))
        ->and($audit->new_values['custom_js_sha256'])->toBe(hash('sha256', $customJs))
        ->and(json_encode($audit->old_values).json_encode($audit->new_values))->not->toContain($customJs);
});

test('partial update preserves omitted custom code fields', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.before { color: red; }',
        'custom_css_enabled' => true,
        'custom_js' => 'window.before = true;',
        'custom_js_enabled' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/custom-code', [
            'custom_css' => '.after { color: green; }',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.custom_css', '.after { color: green; }')
        ->assertJsonPath('data.settings.custom_css_enabled', true)
        ->assertJsonPath('data.settings.custom_js', 'window.before = true;')
        ->assertJsonPath('data.settings.custom_js_enabled', true);
});

test('admin can clear custom code with an empty string', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.remove-me { display: none; }',
        'custom_css_enabled' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/custom-code', ['custom_css' => ''])
        ->assertOk()
        ->assertJsonPath('data.settings.custom_css', '')
        ->assertJsonPath('data.settings.custom_css_enabled', true);

    expect(Setting::query()->where('key', 'custom_css')->value('value'))->toBe('');
});

test('custom code validation rejects invalid values', function (array $payload, string $field): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/custom-code', $payload)
        ->assertUnprocessable()
        ->assertJsonPath("data.errors.{$field}.0", fn (string $message): bool => $message !== '');
})->with([
    'css wrong type' => [['custom_css' => ['invalid']], 'custom_css'],
    'js wrong type' => [['custom_js' => ['invalid']], 'custom_js'],
    'css enabled wrong type' => [['custom_css_enabled' => 'yes'], 'custom_css_enabled'],
    'js enabled wrong type' => [['custom_js_enabled' => 2], 'custom_js_enabled'],
    'css too long' => [['custom_css' => str_repeat('a', 100_001)], 'custom_css'],
    'js too long' => [['custom_js' => str_repeat('a', 100_001)], 'custom_js'],
]);

test('public layout loads each enabled custom asset exactly once and in order', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.custom-public-marker { color: rgb(1, 2, 3); }',
        'custom_css_enabled' => true,
        'custom_js' => 'window.customPublicMarker = true;',
        'custom_js_enabled' => true,
    ]);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect(substr_count($html, 'data-site-custom-css'))->toBe(1)
        ->and(substr_count($html, 'data-site-custom-js'))->toBe(1)
        ->and($html)->not->toContain('.custom-public-marker')
        ->not->toContain('window.customPublicMarker = true;')
        ->and(strpos($html, 'data-site-custom-css'))->toBeGreaterThan(strpos($html, 'resources/css/client.css'))
        ->and(strpos($html, 'data-site-custom-js'))->toBeGreaterThan(strpos($html, 'resources/js/client.js'));
});

test('disabled or empty custom code does not add asset tags', function (array $settings): void {
    app(SettingStore::class)->putMany($settings);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-site-custom-css', false)
        ->assertDontSee('data-site-custom-js', false);
})->with([
    'disabled' => [[
        'custom_css' => '.disabled { color: red; }',
        'custom_css_enabled' => false,
        'custom_js' => 'window.disabled = true;',
        'custom_js_enabled' => false,
    ]],
    'empty' => [[
        'custom_css' => '',
        'custom_css_enabled' => true,
        'custom_js' => '',
        'custom_js_enabled' => true,
    ]],
]);

test('custom code never appears in admin or authentication pages', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.admin-lockout { display: none; }',
        'custom_css_enabled' => true,
        'custom_js' => 'window.adminLockout = true;',
        'custom_js_enabled' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('data-site-custom-css', false)
        ->assertDontSee('data-site-custom-js', false)
        ->assertDontSee('window.adminLockout', false);

    $this->app['auth']->logout();

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertDontSee('data-site-custom-css', false)
        ->assertDontSee('data-site-custom-js', false);
});

test('custom asset endpoints return enabled code with safe response headers', function (): void {
    $customCss = '.endpoint-css { color: green; }';
    $customJs = 'window.endpointJs = true;';
    app(SettingStore::class)->putMany([
        'custom_css' => $customCss,
        'custom_css_enabled' => true,
        'custom_js' => $customJs,
        'custom_js_enabled' => true,
    ]);

    $cssResponse = $this->get(route('site_custom.css'))->assertOk();
    $jsResponse = $this->get(route('site_custom.js'))->assertOk();

    $cssResponse->assertContent($customCss)
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $jsResponse->assertContent($customJs)
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($cssResponse->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($jsResponse->headers->get('Cache-Control'))->toContain('no-cache');
});

test('disabled custom asset endpoints have empty bodies', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.disabled { color: red; }',
        'custom_css_enabled' => false,
        'custom_js' => 'window.disabled = true;',
        'custom_js_enabled' => false,
    ]);

    $this->get(route('site_custom.css'))->assertOk()->assertContent('');
    $this->get(route('site_custom.js'))->assertOk()->assertContent('');
});

test('public system settings never expose raw custom code', function (): void {
    $customCss = '.private-css { display: none; }';
    $customJs = 'window.privateJs = true;';
    app(SettingStore::class)->putMany([
        'custom_css' => $customCss,
        'custom_css_enabled' => true,
        'custom_js' => $customJs,
        'custom_js_enabled' => true,
    ]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/system-settings')->assertOk();

    $response->assertJsonMissingPath('data.settings.custom_css')
        ->assertJsonMissingPath('data.settings.custom_css_enabled')
        ->assertJsonMissingPath('data.settings.custom_js')
        ->assertJsonMissingPath('data.settings.custom_js_enabled');
    expect($response->getContent())->not->toContain($customCss)->not->toContain($customJs);
});

test('custom asset changes are visible immediately after update', function (): void {
    app(SettingStore::class)->putMany([
        'custom_css' => '.version-one {}',
        'custom_css_enabled' => true,
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->get(route('site_custom.css'))->assertContent('.version-one {}');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/custom-code', ['custom_css' => '.version-two {}'])
        ->assertOk();

    $this->get(route('site_custom.css'))->assertContent('.version-two {}');
});
