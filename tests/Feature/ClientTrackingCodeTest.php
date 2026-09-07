<?php

use App\Models\User;
use App\Support\SettingStore;

test('client pages load configured Google Tag Manager and Meta Pixel code', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', [
            'meta_title' => '',
            'meta_description' => '',
            'robots' => 'index,follow',
            'robots_txt' => '',
            'ads_txt' => '',
            'gtm_id' => 'GTM-ABC1234',
            'meta_pixel_id' => '123456789012345',
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.gtm_id', 'GTM-ABC1234')
        ->assertJsonPath('data.settings.meta_pixel_id', '123456789012345');

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-site-gtm')
        ->toContain('data-site-gtm-noscript')
        ->toContain('GTM-ABC1234')
        ->toContain('data-site-meta-pixel')
        ->toContain('data-site-meta-pixel-noscript')
        ->toContain('123456789012345')
        ->toContain("fbq('track', 'PageView')")
        ->and(substr_count($html, 'data-site-gtm>'))->toBe(1)
        ->and(substr_count($html, 'data-site-gtm-noscript'))->toBe(1)
        ->and(substr_count($html, 'data-site-meta-pixel>'))->toBe(1)
        ->and(substr_count($html, 'data-site-meta-pixel-noscript'))->toBe(1)
        ->and(strpos($html, 'data-site-gtm-noscript'))->toBeGreaterThan(strpos($html, '<body'))
        ->and(strpos($html, 'data-site-meta-pixel-noscript'))->toBeGreaterThan(strpos($html, '<body'));
});

test('client tracking code does not load on authentication or admin pages', function (): void {
    app(SettingStore::class)->putMany([
        'gtm_id' => 'GTM-ABC1234',
        'meta_pixel_id' => '123456789012345',
    ]);

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertDontSee('data-site-gtm', false)
        ->assertDontSee('data-site-meta-pixel', false);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.spa'))
        ->assertOk()
        ->assertDontSee('data-site-gtm', false)
        ->assertDontSee('data-site-meta-pixel', false);
});

test('invalid stored tracking identifiers are not rendered', function (): void {
    app(SettingStore::class)->putMany([
        'gtm_id' => '"><script>alert(1)</script>',
        'meta_pixel_id' => '"><script>alert(2)</script>',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-site-gtm', false)
        ->assertDontSee('data-site-meta-pixel', false)
        ->assertDontSee('alert(1)', false)
        ->assertDontSee('alert(2)', false);
});

test('admin SEO settings reject invalid tracking identifiers', function (array $payload, string $field): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', $payload)
        ->assertUnprocessable()
        ->assertJsonPath("data.errors.{$field}.0", fn (string $message): bool => $message !== '');
})->with([
    'invalid GTM ID' => [['gtm_id' => 'not-a-container'], 'gtm_id'],
    'invalid Meta Pixel ID' => [['meta_pixel_id' => 'pixel-123'], 'meta_pixel_id'],
]);
