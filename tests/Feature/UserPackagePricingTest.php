<?php

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
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
