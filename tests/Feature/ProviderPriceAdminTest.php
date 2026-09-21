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
