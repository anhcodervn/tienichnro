<?php

use App\Models\Game;
use App\Models\MemberLevel;
use App\Models\TopupPackage;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('protects member level administration from normal users', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/admin-api/member-levels')->assertForbidden();
});

it('allows admins to manage levels and package prices', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);
    $game = Game::factory()->create();
    $package = TopupPackage::factory()->for($game)->create();

    $createResponse = $this->postJson('/api/admin-api/member-levels', [
        'code' => 'agency',
        'name' => 'Đại lý',
        'rank' => 4,
        'lifetime_threshold' => 2000000,
        'maintenance_amount' => 10000,
        'maintenance_days' => 31,
        'default_discount_bps' => 100,
        'minimum_profit' => 500,
        'color' => '#2563eb',
        'icon' => 'crown',
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated();
    $level = MemberLevel::query()->where('code', 'agency')->firstOrFail();

    $this->putJson("/api/admin-api/member-levels/{$level->id}/packages/{$package->id}", [
        'pricing_mode' => 'fixed',
        'discount_basis_points' => null,
        'fixed_price' => (int) $package->provider_price + 1000,
        'minimum_profit' => 1000,
        'is_active' => true,
    ])->assertSuccessful();

    $this->getJson('/api/admin-api/member-levels')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => 'agency'])
        ->assertJsonFragment(['topup_package_id' => $package->id]);

    expect($createResponse->json('data.level.code'))->toBe('agency');
});

it('allows an admin to lock and clear a user level', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $level = MemberLevel::factory()->create(['rank' => 4, 'lifetime_threshold' => 2000000]);
    Sanctum::actingAs($admin);

    $this->putJson("/api/admin-api/member-levels/users/{$user->id}/assignment", [
        'member_level_id' => $level->id,
        'expires_at' => now()->addMonth()->toISOString(),
        'reason' => 'Đại lý tuyển riêng',
    ])->assertSuccessful()->assertJsonPath('data.member_level.effective_level.id', $level->id);

    $this->putJson("/api/admin-api/member-levels/users/{$user->id}/assignment", [
        'member_level_id' => null,
        'expires_at' => null,
    ])->assertSuccessful();

    expect($user->memberLevelAccount()->first()->manual_level_id)->toBeNull();
});
