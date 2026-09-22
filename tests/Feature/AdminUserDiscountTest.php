<?php

use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;

test('admin lists each user with configured package or global discounts once', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $packageUser = User::factory()->create(['tenant_id' => $main->id, 'full_name' => 'Package Discount User']);
    $bothUser = User::factory()->create(['tenant_id' => $main->id, 'full_name' => 'Both Discount User']);
    $plainUser = User::factory()->create(['tenant_id' => $main->id]);
    $package = TopupPackage::factory()->create();
    $globalPackage = GlobalTopupPackage::factory()->create();

    UserPackagePrice::factory()->for($packageUser)->for($package, 'package')->create([
        'discount_basis_points' => 500,
        'is_active' => true,
    ]);
    UserPackagePrice::factory()->for($bothUser)->for($package, 'package')->create([
        'discount_basis_points' => 750,
        'is_active' => false,
    ]);
    UserGlobalPackagePrice::factory()->for($bothUser)->for($globalPackage, 'globalPackage')->create([
        'discount_basis_points' => 1000,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin-api/users/discounts?per_page=10')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 2)
        ->assertJsonPath('data.stats.discounted_users', 2)
        ->assertJsonPath('data.stats.active_users', 2)
        ->assertJsonPath('data.stats.package_rules', 2)
        ->assertJsonPath('data.stats.global_rules', 1);

    $rows = collect($response->json('data.data'))->keyBy('id');

    expect($rows)->toHaveCount(2)
        ->and($rows)->toHaveKeys([$packageUser->id, $bothUser->id])
        ->and($rows)->not->toHaveKey($plainUser->id)
        ->and($rows[$packageUser->id]['discount_rates'])->toBe([5])
        ->and($rows[$bothUser->id]['package_rules_count'])->toBe(1)
        ->and($rows[$bothUser->id]['active_package_rules_count'])->toBe(0)
        ->and($rows[$bothUser->id]['active_global_rules_count'])->toBe(1)
        ->and($rows[$bothUser->id]['discount_rates'])->toBe([10]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/users/discounts?search=Package%20Discount&scope=packages&rule_status=active')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.id', $packageUser->id);
});

test('admin bulk sets discounts for selected users across custom and global packages', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $firstUser = User::factory()->create(['tenant_id' => $main->id]);
    $secondUser = User::factory()->create(['tenant_id' => $main->id]);
    $untouchedUser = User::factory()->create(['tenant_id' => $main->id]);
    $customGame = Game::factory()->create(['package_mode' => 'custom']);
    $globalGame = Game::factory()->create(['package_mode' => 'global']);
    $customPackages = TopupPackage::factory()->count(2)->for($customGame)->create(['status' => 'active']);
    TopupPackage::factory()->for($customGame)->inactive()->create();
    $globalPackage = GlobalTopupPackage::factory()->create(['status' => 'active']);
    TopupPackage::factory()->for($globalGame)->create([
        'global_topup_package_id' => $globalPackage->id,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->putJson('/api/admin-api/users/discounts/bulk', [
            'user_ids' => [$firstUser->id, $secondUser->id],
            'scope' => 'all',
            'discount_percent' => 8.5,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.users_updated', 2)
        ->assertJsonPath('data.package_rules_upserted', 4)
        ->assertJsonPath('data.global_rules_upserted', 2);

    expect(UserPackagePrice::query()->whereIn('user_id', [$firstUser->id, $secondUser->id])->count())->toBe(4)
        ->and(UserPackagePrice::query()->whereIn('user_id', [$firstUser->id, $secondUser->id])->pluck('topup_package_id')->unique()->sort()->values()->all())
        ->toBe($customPackages->pluck('id')->sort()->values()->all())
        ->and(UserPackagePrice::query()->whereIn('user_id', [$firstUser->id, $secondUser->id])->pluck('discount_basis_points')->unique()->all())->toBe([850])
        ->and(UserGlobalPackagePrice::query()->whereIn('user_id', [$firstUser->id, $secondUser->id])->count())->toBe(2)
        ->and(UserGlobalPackagePrice::query()->whereIn('user_id', [$firstUser->id, $secondUser->id])->pluck('discount_basis_points')->unique()->all())->toBe([850])
        ->and(UserPackagePrice::query()->where('user_id', $untouchedUser->id)->exists())->toBeFalse();
});

test('bulk user discounts validate all user ids before writing', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $member = User::factory()->create(['tenant_id' => $main->id]);
    TopupPackage::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->putJson('/api/admin-api/users/discounts/bulk', [
            'user_ids' => [$member->id, 999999],
            'scope' => 'packages',
            'discount_percent' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_ids');

    expect(UserPackagePrice::query()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('child admin cannot list or bulk update discount users from another website', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $mainUser = User::factory()->create(['tenant_id' => $main->id]);
    $package = TopupPackage::factory()->create(['status' => 'active']);
    UserPackagePrice::factory()->for($mainUser)->for($package, 'package')->create();

    $child = Tenant::factory()->create();
    TenantDomain::factory()->for($child)->create(['domain' => 'discount-agency.test']);
    $childAdmin = User::factory()->create(['tenant_id' => $child->id, 'role' => 'admin']);
    $childUser = User::factory()->create(['tenant_id' => $child->id]);
    UserPackagePrice::factory()->for($childUser)->for($package, 'package')->create();

    $this->actingAs($childAdmin)
        ->getJson('http://discount-agency.test/api/admin-api/users/discounts')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.id', $childUser->id);

    $this->actingAs($childAdmin)
        ->putJson('http://discount-agency.test/api/admin-api/users/discounts/bulk', [
            'user_ids' => [$mainUser->id],
            'scope' => 'packages',
            'discount_percent' => 12,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_ids');

    expect(UserPackagePrice::query()->withoutGlobalScopes()->where('user_id', $mainUser->id)->value('discount_basis_points'))->toBe(500);
});
