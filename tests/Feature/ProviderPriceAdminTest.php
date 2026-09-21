<?php

use App\Enums\TopupProviderType;
use App\Features\Topup\Providers\AccNroVnTopupProvider;
use App\Features\Topup\Providers\MerchantPartnerCardTopupProvider;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

test('provider price comparison is restricted to platform admins', function (): void {
    $this->getJson('/api/admin-api/provider-prices')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/provider-prices')
        ->assertForbidden();
});

test('provider type selects a shared merchant adapter or the dedicated accnro adapter', function (): void {
    $resolver = app(TopupProviderResolver::class);
    $napFf = TopupProvider::factory()->make([
        'slug' => 'napff',
        'type' => TopupProviderType::MerchantPartnerCard,
    ]);
    $accNro = TopupProvider::factory()->make([
        'slug' => 'accnro-source',
        'type' => TopupProviderType::AccNro,
    ]);

    expect($resolver->resolve($napFf))->toBeInstanceOf(MerchantPartnerCardTopupProvider::class)
        ->and($resolver->resolve($accNro))->toBeInstanceOf(AccNroVnTopupProvider::class);
});

test('admin compares active custom and global package prices', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'name' => 'NapFF',
        'slug' => 'napff',
        'balance_status' => 'success',
        'balance_checked_at' => now(),
    ]);
    $cheaperProvider = TopupProvider::factory()->create(['name' => 'NapGame1S', 'slug' => 'napgame1s']);
    $game = Game::factory()->create(['name' => 'Ngọc Rồng']);
    $customPackage = TopupPackage::factory()->for($game)->create([
        'name' => 'Gói riêng 100K',
        'provider_id' => $provider->id,
        'provider_price' => 80000,
        'price' => 90000,
        'original_price' => 100000,
        'status' => 'active',
        'sort_order' => 1,
    ]);
    TopupPackage::factory()->for($game)->create(['status' => 'inactive']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Gói Global 200K',
        'provider_price' => 160000,
        'price' => 180000,
        'original_price' => 200000,
        'status' => 'active',
    ]);
    $customPackage->providerPrices()->create(['topup_provider_id' => $provider->id, 'price' => 80000]);
    $customPackage->providerPrices()->create(['topup_provider_id' => $cheaperProvider->id, 'price' => 79000]);
    $globalPackage->providerPrices()->create(['topup_provider_id' => $provider->id, 'price' => 160000]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/provider-prices')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.packages')
        ->assertJsonPath('data.packages.0.id', $customPackage->id)
        ->assertJsonPath('data.packages.0.scope', 'package')
        ->assertJsonPath("data.packages.0.provider_prices.{$provider->id}", 80000)
        ->assertJsonPath("data.packages.0.provider_prices.{$cheaperProvider->id}", 79000)
        ->assertJsonPath('data.packages.0.best_provider_id', $cheaperProvider->id)
        ->assertJsonPath('data.packages.0.profit', 10000)
        ->assertJsonPath('data.packages.1.id', $globalPackage->id)
        ->assertJsonPath('data.packages.1.scope', 'global')
        ->assertJsonPath('data.providers.0.type', TopupProviderType::MerchantPartnerCard->value)
        ->assertJsonPath('data.providers.0.balance_status', 'success')
        ->assertJsonPath('data.providers.0.balance_error_message', null)
        ->assertJsonPath('data.providers.1.balance_status', 'unchecked');
});

test('admin stores independent quotes and quickly connects the selected provider', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['name' => 'NapGame1S', 'slug' => 'napgame1s']);
    $package = TopupPackage::factory()->create([
        'provider_id' => null,
        'provider_price' => 0,
        'price' => 90000,
        'original_price' => 100000,
        'status' => 'active',
        'sort_order' => 1,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/provider-prices/package/{$package->id}/providers/{$provider->id}", [
            'provider_price' => 81000,
        ])
        ->assertSuccessful()
        ->assertJsonPath("data.provider_prices.{$provider->id}", 81000);

    expect($package->refresh()->provider_id)->toBeNull();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/provider-prices/package/{$package->id}/providers/{$provider->id}/select", [
            'provider_price' => 81000,
            'price' => 88000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.provider_id', $provider->id)
        ->assertJsonPath('data.provider_price', 81000)
        ->assertJsonPath('data.price', 88000)
        ->assertJsonPath('data.profit', 7000);

    expect($package->refresh()->provider_id)->toBe($provider->id)
        ->and((int) $package->provider_price)->toBe(81000)
        ->and((int) $package->price)->toBe(88000)
        ->and($package->providerPrices()->where('topup_provider_id', $provider->id)->count())->toBe(1);
});

test('provider price comparison rejects invalid sale prices and loss-making connections', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create();
    $package = TopupPackage::factory()->create(['original_price' => 100000, 'status' => 'active']);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/provider-prices/package/{$package->id}/providers/{$provider->id}/select", [
            'provider_price' => 95000,
            'price' => 90000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider_price');

    $this->actingAs($admin)
        ->putJson("/api/admin-api/provider-prices/package/{$package->id}", [
            'price' => 110000,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('price');
});

test('selecting a global package provider propagates to its active game package', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['name' => 'NapFF', 'slug' => 'napff']);
    $game = Game::factory()->create(['package_mode' => 'global', 'provider_service_code' => 'nro']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'original_price' => 100000,
        'price' => 90000,
        'status' => 'active',
    ]);
    GlobalTopupPackageGameSetting::factory()->for($game)->create([
        'denomination' => $globalPackage->denomination,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/provider-prices/global/{$globalPackage->id}/providers/{$provider->id}/select", [
            'provider_price' => 80000,
            'price' => 88000,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.provider_id', $provider->id)
        ->assertJsonPath("data.provider_prices.{$provider->id}", 80000);

    $syncedPackage = TopupPackage::query()
        ->whereBelongsTo($game)
        ->whereBelongsTo($globalPackage, 'globalTopupPackage')
        ->firstOrFail();

    expect($syncedPackage->provider_id)->toBe($provider->id)
        ->and((int) $syncedPackage->provider_price)->toBe(80000)
        ->and((int) $syncedPackage->price)->toBe(88000);
});

test('admin refreshes merchant partner card products and maps prices by service code and denomination', function (): void {
    Http::preventStrayRequests();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'name' => 'The9P',
        'slug' => 'the9p',
        'type' => TopupProviderType::MerchantPartnerCard,
        'connection_config' => [
            'base_url' => 'https://provider.test/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    $game = Game::factory()->create(['provider_service_code' => 'nr']);
    $package = TopupPackage::factory()->for($game)->create([
        'provider_id' => $provider->id,
        'denomination' => 100000,
        'provider_price' => 81000,
        'price' => 90000,
        'original_price' => 100000,
        'status' => 'active',
        'sort_order' => 1,
    ]);
    $unsupportedPackage = TopupPackage::factory()->for($game)->create([
        'denomination' => 200000,
        'status' => 'active',
        'sort_order' => 2,
    ]);
    $package->providerPrices()->create(['topup_provider_id' => $provider->id, 'price' => 81000]);
    $unsupportedPackage->providerPrices()->create(['topup_provider_id' => $provider->id, 'price' => 160000]);

    Http::fake([
        'https://provider.test/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'OK',
            'data' => [[
                'name' => 'Ngọc Rồng',
                'service_code' => 'NR',
                'items' => [[
                    'name' => 'Gói 100.000đ',
                    'value' => 100000,
                    'price' => 100000,
                    'discount' => 19.7,
                ]],
            ]],
        ]),
    ]);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/provider-prices/refresh');

    $response
        ->assertSuccessful()
        ->assertJsonPath("data.packages.0.provider_prices.{$provider->id}", 80300)
        ->assertJsonPath('data.providers.0.price_sync_status', 'success')
        ->assertJsonPath("data.sync_results.{$provider->id}.matched_packages", 1);

    expect((int) $package->refresh()->provider_price)->toBe(80300)
        ->and($package->providerPrices()->where('topup_provider_id', $provider->id)->firstOrFail()->available)->toBeTrue()
        ->and($unsupportedPackage->providerPrices()->where('topup_provider_id', $provider->id)->firstOrFail()->available)->toBeFalse();

    Http::assertSent(function (ClientRequest $request): bool {
        return $request->url() === 'https://provider.test/api/rechargews'
            && $request['command'] === 'productlist'
            && $request['partner_id'] === 'partner-123'
            && $request['sign'] === md5('secret-key'.'partner-123'.'productlist');
    });
});

test('one merchant catalog failure does not prevent another provider price refresh', function (): void {
    Http::preventStrayRequests();

    $admin = User::factory()->create(['role' => 'admin']);
    $failedProvider = TopupProvider::factory()->create([
        'connection_config' => ['base_url' => 'https://failed.test/api/rechargews', 'partner_id' => 'failed', 'partner_key' => 'secret'],
    ]);
    $workingProvider = TopupProvider::factory()->create([
        'connection_config' => ['base_url' => 'https://working.test/api/rechargews', 'partner_id' => 'working', 'partner_key' => 'secret'],
    ]);
    $game = Game::factory()->create(['provider_service_code' => 'nr']);
    $package = TopupPackage::factory()->for($game)->create(['denomination' => 100000, 'status' => 'active']);

    Http::fake(function (ClientRequest $request) {
        if ($request->url() === 'https://failed.test/api/rechargews') {
            return Http::response(['status' => 'error', 'message' => 'maintenance'], 503);
        }

        return Http::response([
            'status' => 'success',
            'data' => [[
                'service_code' => 'nr',
                'items' => [['value' => 100000, 'price' => 79000]],
            ]],
        ]);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin-api/provider-prices/refresh')
        ->assertSuccessful()
        ->assertJsonPath("data.sync_results.{$failedProvider->id}.status", 'failed')
        ->assertJsonPath("data.sync_results.{$workingProvider->id}.status", 'success')
        ->assertJsonPath("data.packages.0.provider_prices.{$workingProvider->id}", 79000);

    expect($failedProvider->refresh()->price_sync_status)->toBe('failed')
        ->and($workingProvider->refresh()->price_sync_status)->toBe('success')
        ->and($package->providerPrices()->where('topup_provider_id', $workingProvider->id)->exists())->toBeTrue();
});

test('global package price is refreshed only when all related services have the same provider price', function (): void {
    Http::preventStrayRequests();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'connection_config' => ['base_url' => 'https://global.test/api/rechargews', 'partner_id' => 'global', 'partner_key' => 'secret'],
    ]);
    $firstGame = Game::factory()->create(['provider_service_code' => 'nro']);
    $secondGame = Game::factory()->create(['provider_service_code' => 'nso']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'provider_id' => $provider->id,
        'denomination' => 100000,
        'provider_price' => 82000,
        'status' => 'active',
    ]);
    TopupPackage::factory()->for($firstGame)->create([
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
        'status' => 'active',
    ]);
    TopupPackage::factory()->for($secondGame)->create([
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
        'status' => 'active',
    ]);

    Http::fakeSequence('https://global.test/api/rechargews')
        ->push([
            'status' => 'success',
            'data' => [
                ['service_code' => 'nro', 'items' => [['value' => 100000, 'price' => 80000]]],
                ['service_code' => 'nso', 'items' => [['value' => 100000, 'price' => 80000]]],
            ],
        ])
        ->push([
            'status' => 'success',
            'data' => [
                ['service_code' => 'nro', 'items' => [['value' => 100000, 'price' => 79000]]],
                ['service_code' => 'nso', 'items' => [['value' => 100000, 'price' => 81000]]],
            ],
        ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/provider-prices/refresh')
        ->assertSuccessful()
        ->assertJsonPath("data.sync_results.{$provider->id}.matched_global_packages", 1);

    expect((int) $globalPackage->refresh()->provider_price)->toBe(80000)
        ->and($globalPackage->providerPrices()->where('topup_provider_id', $provider->id)->firstOrFail()->available)->toBeTrue();

    $this->actingAs($admin)
        ->postJson('/api/admin-api/provider-prices/refresh')
        ->assertSuccessful()
        ->assertJsonPath("data.sync_results.{$provider->id}.matched_global_packages", 0);

    expect($globalPackage->providerPrices()->where('topup_provider_id', $provider->id)->firstOrFail()->available)->toBeFalse();
});
