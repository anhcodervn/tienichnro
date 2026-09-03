<?php

use App\Models\User;

it('does not expose the retired member level administration endpoints', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/member-levels')
        ->assertNotFound();

    $this->actingAs($admin)
        ->postJson('/api/admin-api/member-levels', [])
        ->assertNotFound();
});
