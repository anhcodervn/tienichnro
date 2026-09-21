<?php

use App\Enums\TopupProviderType;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.internal_cron.key' => 'private-cron-key']);
    Http::preventStrayRequests();
});

test('provider price cron rejects missing wrong and query string keys', function (): void {
    $this->postJson(route('api.cron.provider-prices'))->assertForbidden();
    $this->withHeader('X-Cron-Key', 'wrong-key')
        ->postJson(route('api.cron.provider-prices'))
        ->assertForbidden();
    $this->postJson(route('api.cron.provider-prices', ['key' => 'private-cron-key']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('provider price cron refreshes costs and only raises active package prices to the configured margin', function (): void {
    $provider = TopupProvider::factory()->create([
        'slug' => 'price-cron-provider',
        'type' => TopupProviderType::MerchantPartnerCard,
        'connection_config' => [
            'base_url' => 'https://provider.test/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
            'minimum_profit_percent' => 10,
        ],
    ]);
    $game = Game::factory()->create(['provider_service_code' => 'nr']);
    $adjustedPackage = TopupPackage::factory()->for($game)->create([
        'provider_id' => $provider->id,
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 85000,
        'original_price' => 100000,
        'status' => 'active',
    ]);
    $unchangedPackage = TopupPackage::factory()->for($game)->create([
        'provider_id' => $provider->id,
        'denomination' => 50000,
        'provider_price' => 35000,
        'price' => 48000,
        'original_price' => 50000,
        'status' => 'active',
    ]);
    $cappedPackage = TopupPackage::factory()->for($game)->create([
        'provider_id' => $provider->id,
        'denomination' => 300000,
        'provider_price' => 80000,
        'price' => 90000,
        'original_price' => 100000,
        'status' => 'active',
    ]);
    $inactivePackage = TopupPackage::factory()->for($game)->inactive()->create([
        'provider_id' => $provider->id,
        'denomination' => 500000,
        'provider_price' => 400000,
        'price' => 410000,
        'original_price' => 500000,
    ]);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'provider_id' => $provider->id,
        'provider_service_codes' => ['nr'],
        'denomination' => 200000,
        'provider_price' => 140000,
        'price' => 170000,
        'original_price' => 200000,
        'status' => 'active',
    ]);

    Http::fake([
        'https://provider.test/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => [[
                'service_code' => 'nr',
                'items' => [
                    ['value' => 50000, 'price' => 40000],
                    ['value' => 100000, 'price' => 80000],
                    ['value' => 200000, 'price' => 160000],
                    ['value' => 300000, 'price' => 98000],
                ],
            ]],
        ]),
    ]);

    $this->withToken('private-cron-key')
        ->postJson(route('api.cron.provider-prices'))
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.providers_synced', 1)
        ->assertJsonPath('data.packages_checked', 4)
        ->assertJsonPath('data.packages_adjusted', 3)
        ->assertJsonPath('data.packages_capped', 1)
        ->assertJsonPath("data.price_adjustments.{$provider->id}.minimum_profit_percent", 10)
        ->assertJsonPath("data.price_adjustments.{$provider->id}.enabled", true);

    expect((int) $adjustedPackage->refresh()->provider_price)->toBe(80000)
        ->and((int) $adjustedPackage->price)->toBe(88889)
        ->and((int) $unchangedPackage->refresh()->provider_price)->toBe(40000)
        ->and((int) $unchangedPackage->price)->toBe(48000)
        ->and((int) $cappedPackage->refresh()->provider_price)->toBe(98000)
        ->and((int) $cappedPackage->price)->toBe(100000)
        ->and((int) $inactivePackage->refresh()->provider_price)->toBe(400000)
        ->and((int) $inactivePackage->price)->toBe(410000)
        ->and($globalPackage->refresh()->provider_price)->toBe(160000)
        ->and($globalPackage->price)->toBe(177778);

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://provider.test/api/rechargews'
            && $request['command'] === 'productlist'
            && $request['partner_id'] === 'partner-123'
            && $request['sign'] === md5('secret-keypartner-123productlist');
    });
});

test('provider price cron leaves sale prices unchanged when automatic protection is disabled', function (): void {
    $provider = TopupProvider::factory()->create([
        'type' => TopupProviderType::MerchantPartnerCard,
        'connection_config' => [
            'base_url' => 'https://disabled.test/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
            'minimum_profit_percent' => 0,
        ],
    ]);
    $game = Game::factory()->create(['provider_service_code' => 'nr']);
    $package = TopupPackage::factory()->for($game)->create([
        'provider_id' => $provider->id,
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 80000,
        'original_price' => 100000,
        'status' => 'active',
    ]);

    Http::fake([
        'https://disabled.test/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => [[
                'service_code' => 'nr',
                'items' => [['value' => 100000, 'price' => 90000]],
            ]],
        ]),
    ]);

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->postJson(route('api.cron.provider-prices'))
        ->assertSuccessful()
        ->assertJsonPath("data.price_adjustments.{$provider->id}.enabled", false)
        ->assertJsonPath('data.packages_adjusted', 0);

    expect((int) $package->refresh()->provider_price)->toBe(90000)
        ->and((int) $package->price)->toBe(80000);
});
