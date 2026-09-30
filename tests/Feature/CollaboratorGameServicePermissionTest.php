<?php

use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\Tenant;
use App\Models\User;

test('admin can manage allowed game services for a collaborator', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $collaborator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COLLABORATOR]);
    $regularUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_USER]);
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $firstService = GameService::factory()->for($game)->create(['name' => 'Săn đệ tử']);
    $secondService = GameService::factory()->for($game)->create(['name' => 'Úp sức mạnh', 'status' => 'inactive']);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/users/{$collaborator->id}/game-services")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.services')
        ->assertJsonPath('data.selected_ids', [])
        ->assertJsonFragment([
            'id' => $firstService->id,
            'game_name' => 'Ngọc Rồng Online',
            'name' => 'Săn đệ tử',
            'is_allowed' => false,
        ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/users/{$collaborator->id}/game-services", [
            'game_service_ids' => [$firstService->id, $secondService->id],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.selected_ids.0', $firstService->id)
        ->assertJsonPath('data.selected_ids.1', $secondService->id);

    $this->assertDatabaseHas('collaborator_game_service_permissions', [
        'user_id' => $collaborator->id,
        'game_service_id' => $firstService->id,
    ]);
    $this->assertDatabaseHas('collaborator_game_service_permissions', [
        'user_id' => $collaborator->id,
        'game_service_id' => $secondService->id,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/users/{$regularUser->id}/game-services", [
            'game_service_ids' => [$firstService->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    $this->actingAs($regularUser)
        ->getJson("/api/admin-api/users/{$collaborator->id}/game-services")
        ->assertForbidden();

    $collaborator->forceFill(['game_service_secondary_password' => 'collaborator-secret-123'])->save();

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/users/{$collaborator->id}/role", ['role' => User::ROLE_USER])
        ->assertSuccessful();

    $collaborator->refresh();
    expect($collaborator->allowedGameServices()->count())->toBe(0)
        ->and($collaborator->game_service_secondary_password)->toBeNull();
});

test('collaborator only sees and claims pending orders from allowed services', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $collaborator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COLLABORATOR]);
    $otherCollaborator = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_COLLABORATOR]);
    $game = Game::factory()->create();
    $allowedService = GameService::factory()->for($game)->create();
    $forbiddenService = GameService::factory()->for($game)->create();
    $collaborator->allowedGameServices()->attach($allowedService->id);

    $allowedOrder = GameServiceOrder::factory()->create([
        'game_id' => $game->id,
        'game_service_id' => $allowedService->id,
        'collaborator_id' => null,
        'status' => 'pending',
    ]);
    $forbiddenOrder = GameServiceOrder::factory()->create([
        'game_id' => $game->id,
        'game_service_id' => $forbiddenService->id,
        'collaborator_id' => null,
        'status' => 'pending',
    ]);
    $historicalAssignedOrder = GameServiceOrder::factory()->create([
        'game_id' => $game->id,
        'game_service_id' => $forbiddenService->id,
        'collaborator_id' => $collaborator->id,
        'status' => 'pending',
    ]);
    $collaboratorHeaders = gameServiceSecondaryHeaders($collaborator);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => $allowedOrder->code])
        ->assertJsonFragment(['code' => $historicalAssignedOrder->code])
        ->assertJsonMissing(['code' => $forbiddenOrder->code]);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$forbiddenOrder->code}/start", [], $collaboratorHeaders)
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$allowedOrder->code}/start", [], $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.collaborator_id', $collaborator->id);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-order-chats/collaborators?'.http_build_query([
            'game_service_id' => $forbiddenService->id,
            'include_user_id' => $collaborator->id,
        ]))
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $collaborator->id])
        ->assertJsonMissing(['id' => $otherCollaborator->id]);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$forbiddenOrder->code}", [
            'status' => 'pending',
            'collaborator_id' => $otherCollaborator->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('collaborator_id');

    $otherCollaborator->allowedGameServices()->attach($forbiddenService->id);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$forbiddenOrder->code}", [
            'status' => 'pending',
            'collaborator_id' => $otherCollaborator->id,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.collaborator_id', $otherCollaborator->id);
});
