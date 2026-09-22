<?php

use App\Models\Game;
use App\Models\TopupPackage;
use App\Models\User;

it('backfills one safe per-account quantity range for each game', function (): void {
    $game = Game::factory()->create([
        'min_quantity' => 1,
        'max_quantity' => 10,
    ]);

    TopupPackage::factory()->for($game)->create([
        'min_quantity' => 1,
        'max_quantity' => 5,
        'status' => 'active',
    ]);
    TopupPackage::factory()->for($game)->create([
        'min_quantity' => 2,
        'max_quantity' => 4,
        'status' => 'active',
    ]);
    TopupPackage::factory()->for($game)->create([
        'min_quantity' => 3,
        'max_quantity' => 3,
        'status' => 'inactive',
    ]);

    $migration = require database_path('migrations/2026_09_22_163433_backfill_game_quantity_limits_from_packages.php');
    $migration->up();

    expect($game->refresh()->min_quantity)->toBe(2)
        ->and($game->max_quantity)->toBe(4);
});

it('rejects package-level quantity limits through the admin api', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['package_mode' => 'custom']);

    $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', [
        'game_id' => $game->id,
        'name' => 'Gói riêng',
        'denomination' => 10000,
        'provider_price' => 8000,
        'price' => 9000,
        'original_price' => 10000,
        'min_quantity' => 2,
        'max_quantity' => 4,
        'status' => 'active',
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['min_quantity', 'max_quantity']);
});

it('requires a valid per-account quantity range on a game', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();

    $this->actingAs($admin)->patchJson("/api/admin-api/games/{$game->id}", [
        'name' => $game->name,
        'slug' => $game->slug,
        'reward_label' => $game->reward_label,
        'min_quantity' => 6,
        'max_quantity' => 5,
        'checkout_fields' => $game->checkoutFields(),
        'status' => $game->status,
        'sort_order' => $game->sort_order,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('max_quantity');
});
