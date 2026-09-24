<?php

use App\Models\User;

test('guest cannot open the removed website chat page', function (): void {
    $this->get('/chat')->assertNotFound();
});

test('authenticated user cannot open the removed website chat page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/chat')
        ->assertNotFound();
});
