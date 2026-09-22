<?php

use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Features\Topup\Services\OrderPricingService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GlobalTopupPackage;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;

it('protects and saves one game reward matrix without changing another game', function (): void {
    $this->getJson('/api/admin-api/global-topup-rewards')->assertUnauthorized();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['slug' => 'the9p']);
    $firstGame = Game::factory()->create([
        'package_mode' => 'global',
        'name' => 'Ngọc Rồng Online',
        'provider_service_code' => 'nro',
    ]);
    $secondGame = Game::factory()->create(['package_mode' => 'global', 'name' => 'Avatar Musik']);
    $packages = collect([
        GlobalTopupPackage::factory()->create([
            'provider_id' => $provider->id,
            'denomination' => 100000,
            'metadata' => ['requires_game_rewards' => true],
        ]),
        GlobalTopupPackage::factory()->create([
            'provider_id' => $provider->id,
            'denomination' => 200000,
            'metadata' => ['requires_game_rewards' => true],
        ]),
    ]);

    foreach ($packages as $package) {
        GlobalTopupPackageGameSetting::factory()->for($package)->for($secondGame)->create([
            'denomination' => $package->denomination,
            'provider_service_code' => 'avatar',
            'receives' => [[
                'code' => 'GM',
                'label' => 'Gem mở',
                'base_amount' => 50,
                'reward_x2_amount' => 70,
                'reward_x3_amount' => 90,
                'first_topup_reward_amount' => 100,
            ]],
        ]);
        app(GlobalTopupPackageSyncService::class)->syncPackageForGame($package, $firstGame);
    }
    $untouchedSetting = GlobalTopupPackageGameSetting::factory()->for($firstGame)->create([
        'denomination' => $packages->last()->denomination,
        'provider_service_code' => 'nro-old',
    ]);
    $mapping = TopupPackage::query()
        ->whereBelongsTo($packages->first(), 'globalTopupPackage')
        ->whereBelongsTo($firstGame)
        ->firstOrFail();

    $catalogResponse = $this->actingAs($admin)->getJson('/api/admin-api/global-topup-rewards')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $firstGame->id, 'name' => 'Ngọc Rồng Online'])
        ->assertJsonFragment(['denomination' => 100000]);
    $firstGameCatalog = collect($catalogResponse->json('data.games'))->firstWhere('id', $firstGame->id);

    expect(array_key_exists('provider_id', $firstGameCatalog['denominations'][0]))->toBeFalse()
        ->and(array_key_exists('provider_service_code', $firstGameCatalog['reward_settings'][0]))->toBeFalse();

    $payload = [
        'packages' => [[
            'denomination' => $packages->first()->denomination,
            'receives' => [[
                'code' => 'nx',
                'label' => 'Ngọc xanh',
                'base_amount' => 150,
                'reward_x2_amount' => 195,
                'reward_x3_amount' => 250,
                'first_topup_reward_amount' => 300,
            ]],
        ]],
    ];

    $this->actingAs($admin)
        ->putJson("/api/admin-api/global-topup-rewards/{$firstGame->id}", $payload)
        ->assertSuccessful();

    $firstSetting = GlobalTopupPackageGameSetting::query()
        ->where('denomination', $packages->first()->denomination)
        ->whereBelongsTo($firstGame)
        ->firstOrFail();
    $secondGameSetting = GlobalTopupPackageGameSetting::query()
        ->where('denomination', $packages->first()->denomination)
        ->whereBelongsTo($secondGame)
        ->firstOrFail();
    $mapping->refresh();
    expect($firstSetting->provider_service_code)->toBeNull()
        ->and(data_get($firstSetting->receives, '0.code'))->toBe('NX')
        ->and(data_get($firstSetting->receives, '0.reward_x3_amount'))->toBe(250)
        ->and(data_get($secondGameSetting->receives, '0.code'))->toBe('GM')
        ->and(data_get($secondGameSetting->receives, '0.reward_x3_amount'))->toBe(90)
        ->and($untouchedSetting->refresh()->provider_service_code)->toBe('nro-old')
        ->and($mapping->status)->toBe('active')
        ->and($mapping->providerServiceCode())->toBe('nro')
        ->and(data_get($mapping->metadata, 'global_receives.0.code'))->toBe('NX');
});

it('validates duplicate reward codes without requiring provider configuration', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['package_mode' => 'global']);
    $provider = TopupProvider::factory()->create(['slug' => 'the9p']);
    $package = GlobalTopupPackage::factory()->create(['provider_id' => $provider->id]);
    app(GlobalTopupPackageSyncService::class)->syncPackageForGame($package, $game);

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-rewards/{$game->id}", [
        'packages' => [[
            'denomination' => $package->denomination,
            'receives' => [
                ['code' => 'gm', 'label' => 'Gem mở', 'base_amount' => 100],
                ['code' => 'GM', 'label' => 'Gem khóa', 'base_amount' => 50],
            ],
        ]],
    ])->assertUnprocessable();
});

it('applies rewards to custom games by their actual card denomination', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['package_mode' => 'custom', 'name' => 'Ninja School']);
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'global_topup_package_id' => null,
        'denomination' => 10000,
        'carot_amount' => 1,
        'reward_x2_amount' => 2,
        'reward_x3_amount' => 3,
    ]);

    $this->actingAs($admin)->getJson('/api/admin-api/global-topup-rewards')
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $game->id, 'name' => 'Ninja School'])
        ->assertJsonFragment(['denomination' => 10000]);

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-rewards/{$game->id}", [
        'packages' => [[
            'denomination' => 10000,
            'receives' => [[
                'code' => 'LUONG',
                'label' => 'Lượng',
                'base_amount' => 100,
                'reward_x2_amount' => 200,
                'reward_x3_amount' => 300,
                'first_topup_reward_amount' => 250,
            ]],
        ]],
    ])->assertSuccessful();

    $quote = app(OrderPricingService::class)->quote(
        gameId: $game->id,
        serverId: $server->id,
        packageId: $package->id,
        quantity: 1,
    );

    expect($quote['package']->rewardDisplay())->toBe('100 Lượng')
        ->and($quote['package']->rewardDisplay('reward_x2_amount'))->toBe('200 Lượng')
        ->and($quote['package']->rewardDisplay('reward_x3_amount'))->toBe('300 Lượng')
        ->and($quote['package']->rewardDisplay('first_topup_reward_amount'))->toBe('250 Lượng');
});

it('keeps game rewards when only global package prices are updated', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['package_mode' => 'global']);
    $package = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $setting = GlobalTopupPackageGameSetting::factory()->for($package)->for($game)->create([
        'denomination' => $package->denomination,
    ]);

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-packages/{$package->id}", [
        'provider_id' => null,
        'name' => $package->name,
        'code' => $package->code,
        'denomination' => $package->denomination,
        'provider_price' => 70000,
        'price' => 88000,
        'original_price' => 100000,
        'description' => null,
        'bonus_text' => null,
        'status' => 'active',
        'sort_order' => 0,
        'metadata' => [],
    ])->assertSuccessful();

    expect($setting->refresh()->receives)->not->toBeEmpty();
});
