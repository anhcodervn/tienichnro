<?php

use App\Models\User;

function validSeoInlineCodePayload(array $overrides = []): array
{
    return [
        'meta_title' => '',
        'meta_description' => '',
        'robots' => 'index,follow',
        'robots_txt' => '',
        'ads_txt' => '',
        'gtm_id' => '',
        'meta_pixel_id' => '',
        'custom_head_tags' => '',
        'custom_script' => '',
        ...$overrides,
    ];
}

test('admin can save inline SEO code that renders in client view source', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $headTags = <<<'HTML'
<meta name="google-adsense-account" content="ca-pub-4352299256001618">
<meta property="og:locale" content="Tiáº¿ng Viá»‡t &amp; English">
HTML;
    $script = <<<'HTML'
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-6326EXQD65"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', 'G-6326EXQD65');
</script>
HTML;

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', validSeoInlineCodePayload([
            'custom_head_tags' => $headTags,
            'custom_script' => $script,
        ]))
        ->assertOk()
        ->assertJsonPath('data.settings.custom_head_tags', $headTags)
        ->assertJsonPath('data.settings.custom_script', $script);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('<!-- custom-head-tags -->')
        ->toContain($headTags)
        ->toContain('<!-- custom-script -->')
        ->toContain($script)
        ->and(strpos($html, $headTags))->toBeLessThan(strpos($html, '</head>'))
        ->and(strpos($html, $script))->toBeGreaterThan(strpos($html, '<body'))
        ->and(strpos($html, $script))->toBeLessThan(strpos($html, '</body>'));
});

test('inline SEO code does not render on authentication or admin pages', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', validSeoInlineCodePayload([
            'custom_head_tags' => '<meta name="client-marker" content="head">',
            'custom_script' => '<script>window.clientMarker = true;</script>',
        ]))
        ->assertOk();

    $this->app['auth']->forgetGuards();

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertDontSee('client-marker', false)
        ->assertDontSee('window.clientMarker', false);

    $this->actingAs($admin)
        ->get(route('admin.spa'))
        ->assertOk()
        ->assertDontSee('client-marker', false)
        ->assertDontSee('window.clientMarker', false);
});

test('custom header tags reject unsafe or unsupported HTML', function (string $headTags): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', validSeoInlineCodePayload(['custom_head_tags' => $headTags]))
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.custom_head_tags.0', fn (string $message): bool => $message !== '');
})->with([
    'script tag' => '<script>alert(1)</script>',
    'link tag' => '<link rel="preconnect" href="https://example.com">',
    'http equiv' => '<meta http-equiv="refresh" content="0;url=https://example.com">',
    'event handler' => '<meta name="description" content="test" onload="alert(1)">',
    'more than twenty tags' => implode("\n", array_fill(0, 21, '<meta name="test" content="value">')),
]);
