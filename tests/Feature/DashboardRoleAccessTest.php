<?php

use App\Models\Tenant;
use App\Models\User;

test('game service dashboard api only allows admin and ctv roles', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_USER]);
    $ctv = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COLLABORATOR]);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);

    $this->actingAs($user)
        ->getJson('http://napcarot.com/api/client/affiliate/game-service-dashboard')
        ->assertForbidden();

    $this->actingAs($user)
        ->getJson('http://napcarot.com/api/client/affiliate/game-service-announcements')
        ->assertForbidden();

    $this->actingAs($ctv)
        ->getJson('http://napcarot.com/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->actingAs($ctv)
        ->getJson('http://napcarot.com/api/client/affiliate/game-service-announcements')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('status', true);
});

test('admin can assign and remove the ctv role from a regular user', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->patchJson("http://napcarot.com/api/admin-api/users/{$user->id}/role", ['role' => User::ROLE_COLLABORATOR])
        ->assertSuccessful()
        ->assertJsonPath('data.user.role', User::ROLE_COLLABORATOR);

    expect($user->refresh()->role)->toBe(User::ROLE_COLLABORATOR);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/users?role=ctv')
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.id', $user->id);

    $this->actingAs($admin)
        ->patchJson("http://napcarot.com/api/admin-api/users/{$user->id}/role", ['role' => User::ROLE_USER])
        ->assertSuccessful();

    expect($user->refresh()->role)->toBe(User::ROLE_USER);
});

test('role management cannot promote users to admin or modify an admin account', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_USER]);

    $this->actingAs($admin)
        ->patchJson("http://napcarot.com/api/admin-api/users/{$user->id}/role", ['role' => User::ROLE_ADMIN])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->patchJson("http://napcarot.com/api/admin-api/users/{$admin->id}/role", ['role' => User::ROLE_COLLABORATOR])
        ->assertUnprocessable();

    expect($admin->refresh()->role)->toBe(User::ROLE_ADMIN)
        ->and($user->refresh()->role)->toBe(User::ROLE_USER);
});
