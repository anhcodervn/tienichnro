<?php

use App\Models\User;
use App\Support\SettingStore;

test('AdSense is disabled by default in general settings and public HTML', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson('/api/admin-api/settings/general')->assertOk()
        ->assertJsonPath('data.settings.adsense_enabled', false)
        ->assertJsonPath('data.settings.adsense_client_id', '');
    $this->get(route('home'))->assertOk()->assertDontSee('data-site-adsense', false);
});

test('admin can save AdSense configuration and load it once on public pages', function (string $tab): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $settings = $this->getJson('/api/admin-api/settings/'.$tab)->assertOk()->json('data.settings');
    $payload = [...$settings, 'site_name' => 'Test site', 'adsense_enabled' => true, 'adsense_client_id' => 'ca-pub-1234567890123456'];
    $this->patchJson('/api/admin-api/settings/'.$tab, $payload)->assertOk()
        ->assertJsonPath('data.settings.adsense_enabled', true)
        ->assertJsonPath('data.settings.adsense_client_id', $payload['adsense_client_id']);
    $this->getJson('/api/admin-api/settings/general')->assertOk()
        ->assertJsonPath('data.settings.adsense_client_id', $payload['adsense_client_id']);
    expect(app(SettingStore::class)->get('adsense_enabled'))->toBeTrue();
    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->toContain('name="google-adsense-account" content="ca-pub-1234567890123456"')
        ->toContain('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1234567890123456')
        ->toContain('crossorigin="anonymous" data-site-adsense')
        ->and(substr_count($html, 'data-site-adsense'))->toBe(1)
        ->and(strpos($html, 'data-site-adsense'))->toBeLessThan(strpos($html, '</head>'));
    $this->patchJson('/api/admin-api/settings/'.$tab, [...$payload, 'adsense_enabled' => false])->assertOk();
    $this->get(route('home'))->assertOk()->assertDontSee('data-site-adsense', false);
})->with(['general', 'system']);

test('invalid AdSense configuration is rejected without persisting settings', function (array $extra, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->patchJson('/api/admin-api/settings/general', [
            'site_name' => 'Test site', 'site_active' => true, 'allow_register' => false,
            'adsense_enabled' => true, 'adsense_client_id' => 'ca-pub-1234567890123456', ...$extra,
        ])->assertUnprocessable()->assertJsonPath('data.errors.'.$field.'.0', fn (string $message): bool => $message !== '');
    expect(app(SettingStore::class)->get('adsense_client_id'))->toBeNull();
})->with([
    [['adsense_client_id' => ''], 'adsense_client_id'],
    [['adsense_client_id' => 'AW-1234567890'], 'adsense_client_id'],
    [['adsense_client_id' => 'ca-pub-123'], 'adsense_client_id'],
    [['adsense_client_id' => '"><script>alert(1)</script>'], 'adsense_client_id'],
    [['adsense_enabled' => 'yes'], 'adsense_enabled'],
]);

test('AdSense is excluded from authentication and admin pages', function (): void {
    app(SettingStore::class)->putMany(['adsense_enabled' => true, 'adsense_client_id' => 'ca-pub-1234567890123456']);
    $this->get(route('auth.login'))->assertOk()->assertDontSee('data-site-adsense', false);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.spa'))->assertOk()->assertDontSee('data-site-adsense', false);
});

test('invalid stored AdSense identifiers never render a script', function (): void {
    app(SettingStore::class)->putMany(['adsense_enabled' => true, 'adsense_client_id' => '"><script>alert(1)</script>']);
    $this->get(route('home'))->assertOk()->assertDontSee('data-site-adsense', false)->assertDontSee('alert(1)', false);
});

test('AdSense settings require an administrator', function (): void {
    $this->patchJson('/api/admin-api/settings/general', [])->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->patchJson('/api/admin-api/settings/general', [])->assertForbidden();
});
