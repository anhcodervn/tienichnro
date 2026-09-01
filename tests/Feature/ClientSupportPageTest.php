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
        ->assertViewIs('client.support.index')
        ->assertSee('data-support-chat', false)
        ->assertSee(route('client.support.index'), false)
        ->assertSee("users.{$user->id}.support", false)
        ->assertSee('Chat trực tiếp với hỗ trợ');
});
