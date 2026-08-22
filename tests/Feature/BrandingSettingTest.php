<?php

use App\Models\User;

test('only admins can manage shared branding settings', function (): void {
    $this->getJson('/api/admin-api/settings/branding')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/branding', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/branding')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/branding', [])->assertForbidden();
});

test('branding settings render the correct logo favicon and shared image on blade pages', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $branding = [
        'light_logo' => '/uploads/branding/logo-light.webp',
        'dark_logo' => '/uploads/branding/logo-dark.webp',
        'favicon' => '/uploads/branding/favicon.webp',
        'og_image' => '/uploads/branding/share-background.webp',
        'color_primary' => '#0F172A',
        'color_accent' => '#2563EB',
        'color_surface' => '#F8FAFC',
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/branding', $branding)
        ->assertOk()
        ->assertJsonPath('data.settings.dark_logo', $branding['dark_logo'])
        ->assertJsonPath('data.settings.favicon', $branding['favicon'])
        ->assertJsonPath('data.settings.og_image', $branding['og_image']);

    $branding['og_image'] = '/uploads/branding/share-background-updated.webp';

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/branding', ['og_image' => $branding['og_image']])
        ->assertOk()
        ->assertJsonPath('data.settings.dark_logo', $branding['dark_logo'])
        ->assertJsonPath('data.settings.favicon', $branding['favicon'])
        ->assertJsonPath('data.settings.og_image', $branding['og_image']);

    $this->app['auth']->guard('web')->logout();
    $this->app['auth']->forgetGuards();
    $this->app['session']->flush();

    $this->get(route('auth.login'))
        ->assertOk()
        ->assertSee('<img src="'.$branding['dark_logo'].'" alt="', false)
        ->assertSee('class="h-auto w-32 shrink-0 object-contain object-left sm:w-40"', false)
        ->assertDontSee($branding['light_logo'])
        ->assertSee('<link rel="icon" href="'.$branding['favicon'].'">', false)
        ->assertSee('<link rel="shortcut icon" href="'.$branding['favicon'].'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.$branding['favicon'].'">', false)
        ->assertSee('<meta property="og:image" content="'.url($branding['og_image']).'">', false)
        ->assertSee('<meta name="twitter:image" content="'.url($branding['og_image']).'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});
