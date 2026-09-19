<?php

use App\Models\User;

test('guest is redirected to login when opening the support chat page', function (): void {
    $this->get(route('client.support.chat'))->assertRedirect(route('login'));
});

test('authenticated user can open the realtime support chat page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('client.support.chat'))
        ->assertOk()
        ->assertViewIs('app')
        ->assertSee('id="app"', false)
        ->assertSee('noindex,nofollow', false)
        ->assertDontSee('data-mobile-bottom-nav', false)
        ->assertDontSee('data-floating-support', false)
        ->assertDontSee('assets/libs/tinymce/tinymce.min.js', false);
});
