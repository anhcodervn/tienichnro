<?php

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\ApiKey;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;
use App\Utils\Site;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

function userPricingApiCredentials(User $user): array
{
    $secret = 'ncs_'.Str::random(64);
    $apiKey = ApiKey::factory()->for($user)->create([
        'permissions' => ['catalog:read'],
        'api_secret_hash' => Hash::make($secret),
    ]);

    return [
        'X-API-KEY' => $apiKey->api_key,
        'X-API-SECRET' => $secret,
    ];
}

test('member discount applies to storefront and api pricing', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $package = TopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'provider_price' => 70000,
    ]);
    UserPackagePrice::factory()->for($user)->for($package, 'package')->create([
        'discount_basis_points' => 1000,
    ]);

    $price = Site::for($main, fn (): array => app(TopupPackagePricingService::class)->resolve($package, $user));

    expect($price['final_price'])->toBe(81000)
        ->and($price['user_discount_amount'])->toBe(9000);

    $this->withHeaders(userPricingApiCredentials($user))
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('data.0.packages.0.sale_price', 81000);
});

test('member minimum profit prevents a discount from going below provider cost', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $package = TopupPackage::factory()->create([
        'price' => 90000,
        'provider_price' => 80000,
    ]);
    UserPackagePrice::factory()->for($user)->for($package, 'package')->create([
        'discount_basis_points' => 5000,
        'minimum_profit' => 3000,
    ]);

    $price = Site::for($main, fn (): array => app(TopupPackagePricingService::class)->resolve($package, $user));

    expect($price['final_price'])->toBe(83000);
});

test('billing account discount becomes cost for every linked child website', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $firstTenant = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    $secondTenant = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    $package = TopupPackage::factory()->create([
        'price' => 90000,
        'provider_price' => 70000,
    ]);
    UserPackagePrice::factory()->for($billingUser)->for($package, 'package')->create([
        'discount_basis_points' => 1000,
    ]);
    TenantPackagePrice::factory()->for($firstTenant)->for($package, 'package')->create(['markup_amount' => 5000]);
    TenantPackagePrice::factory()->for($secondTenant)->for($package, 'package')->create(['markup_amount' => 9000]);

    $firstPrice = Site::for($firstTenant, fn (): array => app(TopupPackagePricingService::class)->resolve($package));
    $secondPrice = Site::for($secondTenant, fn (): array => app(TopupPackagePricingService::class)->resolve($package));

    expect($firstPrice['tenant_cost_price'])->toBe(81000)
        ->and($firstPrice['final_price'])->toBe(86000)
        ->and($secondPrice['tenant_cost_price'])->toBe(81000)
        ->and($secondPrice['final_price'])->toBe(90000);
});

test('child admin can manage pricing only for members of their website', function (): void {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->for($tenant)->create(['domain' => 'member-price.test']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);
    $mainMember = User::factory()->create();
    $package = TopupPackage::factory()->create(['price' => 90000, 'provider_price' => 70000]);

    $this->actingAs($admin)->putJson("http://member-price.test/api/admin-api/users/{$member->id}/prices/{$package->id}", [
        'pricing_mode' => 'discount',
        'discount_percent' => 7.5,
        'fixed_price' => null,
        'minimum_profit' => 1000,
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.prices.0.discount_percent', 7.5);

    expect(UserPackagePrice::query()->where('user_id', $member->id)->value('discount_basis_points'))->toBe(750);

    $this->actingAs($admin)->putJson("http://member-price.test/api/admin-api/users/{$mainMember->id}/prices/{$package->id}", [
        'pricing_mode' => 'discount',
        'discount_percent' => 10,
        'minimum_profit' => 0,
        'is_active' => true,
    ])->assertNotFound();
});

test('global package member discount applies to every game using that package', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'provider_price' => 70000,
    ]);
    $games = Game::factory()->count(2)->create(['package_mode' => 'global']);
    $packages = $games->map(function (Game $game) use ($globalPackage): TopupPackage {
        GlobalTopupPackageGameSetting::factory()->for($game)->create(['denomination' => $globalPackage->denomination]);

        return TopupPackage::factory()->for($game)->create([
            'global_topup_package_id' => $globalPackage->id,
            'denomination' => 100000,
        ]);
    });
    UserGlobalPackagePrice::factory()->for($user)->for($globalPackage, 'globalPackage')->create([
        'discount_basis_points' => 1000,
    ]);

    $prices = $packages->map(fn (TopupPackage $package): array => Site::for(
        $main,
        fn (): array => app(TopupPackagePricingService::class)->resolve($package, $user),
    ));

    expect($prices->pluck('final_price')->all())->toBe([81000, 81000])
        ->and($prices->pluck('user_pricing_source')->all())->toBe(['global', 'global']);
});

test('game package member price overrides the global member discount', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $game = Game::factory()->create(['package_mode' => 'global']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'provider_price' => 70000,
    ]);
    GlobalTopupPackageGameSetting::factory()->for($game)->create(['denomination' => $globalPackage->denomination]);
    $package = TopupPackage::factory()->for($game)->create([
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
    ]);
    UserGlobalPackagePrice::factory()->for($user)->for($globalPackage, 'globalPackage')->create([
        'discount_basis_points' => 1000,
    ]);
    UserPackagePrice::factory()->for($user)->for($package, 'package')->create([
        'discount_basis_points' => 500,
    ]);

    $price = Site::for($main, fn (): array => app(TopupPackagePricingService::class)->resolve($package, $user));

    expect($price['final_price'])->toBe(85500)
        ->and($price['user_pricing_source'])->toBe('package');
});

test('platform admin can configure each global package price for a member', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $member = User::factory()->create(['tenant_id' => $main->id]);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Global 100K',
        'denomination' => 100000,
        'price' => 90000,
        'provider_price' => 70000,
        'status' => 'active',
    ]);
    $secondGlobalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Global 200K',
        'denomination' => 200000,
        'price' => 180000,
        'provider_price' => 140000,
        'status' => 'active',
    ]);
    $this->actingAs($admin)->putJson("/api/admin-api/users/{$member->id}/global-prices/{$globalPackage->id}", [
        'pricing_mode' => 'discount',
        'discount_percent' => 8.5,
        'fixed_price' => null,
        'minimum_profit' => 1000,
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.global_packages.0.name', 'Global 100K')
        ->assertJsonPath('data.global_packages.0.discount_percent', 8.5)
        ->assertJsonPath('data.global_packages.0.minimum_profit', 1000)
        ->assertJsonPath('data.global_packages.0.member_price', 82350)
        ->assertJsonPath('data.global_packages.0.discount_amount', 7650)
        ->assertJsonPath('data.global_packages.1.name', 'Global 200K')
        ->assertJsonPath('data.global_packages.1.member_price', 180000)
        ->assertJsonPath('data.global_packages.1.is_active', false);

    $this->actingAs($admin)->putJson("/api/admin-api/users/{$member->id}/global-prices/{$secondGlobalPackage->id}", [
        'pricing_mode' => 'fixed',
        'discount_percent' => null,
        'fixed_price' => 150000,
        'minimum_profit' => 1000,
        'is_active' => true,
    ])->assertOk()
        ->assertJsonPath('data.global_packages.0.member_price', 82350)
        ->assertJsonPath('data.global_packages.1.member_price', 150000)
        ->assertJsonPath('data.global_packages.1.pricing_mode', 'fixed');

    expect(UserGlobalPackagePrice::query()->where('user_id', $member->id)->count())->toBe(2)
        ->and(UserGlobalPackagePrice::query()->where('user_id', $member->id)->where('global_topup_package_id', $globalPackage->id)->value('discount_basis_points'))->toBe(850)
        ->and(UserGlobalPackagePrice::query()->where('user_id', $member->id)->where('global_topup_package_id', $secondGlobalPackage->id)->value('fixed_price'))->toBe(150000);
});
