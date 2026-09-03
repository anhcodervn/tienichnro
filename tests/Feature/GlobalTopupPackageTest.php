<?php

use App\Enums\PaymentMethod;
use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Features\Topup\Services\OrderPricingService;
use App\Features\Topup\Services\OrderService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GlobalTopupPackage;
use App\Models\GlobalTopupPackageGameSetting;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('protects and lets admins manage global topup packages', function () {
    $this->getJson('/api/admin-api/global-topup-packages')->assertUnauthorized();

    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['slug' => 'the9p']);
    $game = Game::factory()->create([
        'package_mode' => 'global',
        'provider_service_code' => 'nro',
    ]);
    TopupPackage::factory()->for($game)->inactive()->create([
        'global_topup_package_id' => null,
        'provider_id' => $provider->id,
        'denomination' => 100000,
    ]);

    $packageResponse = $this->actingAs($admin)->postJson('/api/admin-api/global-topup-packages', [
        'provider_id' => $provider->id,
        'name' => 'Carot Teamobi 100K',
        'code' => 'carot-teamobi-100k',
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 90000,
        'description' => 'Dùng chung cho các game Teamobi',
        'bonus_text' => 'Nhận ngay Carot',
        'min_quantity' => 1,
        'max_quantity' => 10,
        'status' => 'active',
        'sort_order' => 1,
        'metadata' => [],
    ])->assertCreated();
    $globalPackage = GlobalTopupPackage::query()->findOrFail($packageResponse->json('data.global_package.id'));
    $globalPackage->forceFill(['original_price' => $globalPackage->price])->save();

    $mapping = TopupPackage::query()
        ->whereBelongsTo($game)
        ->whereBelongsTo($globalPackage, 'globalTopupPackage')
        ->firstOrFail();
    expect($mapping->status)->toBe('inactive')
        ->and($mapping->provider_service_code)->toBeNull()
        ->and($mapping->providerServiceCode())->toBe('nro');

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-rewards/{$game->id}", [
        'packages' => [[
            'denomination' => $globalPackage->denomination,
            'receives' => [[
                'code' => 'NX',
                'label' => 'Ngọc xanh',
                'base_amount' => 195,
                'reward_x2_amount' => 345,
                'reward_x3_amount' => 495,
                'first_topup_reward_amount' => 390,
            ]],
        ]],
    ])->assertSuccessful();

    $this->actingAs($admin)->getJson('/api/admin-api/global-topup-packages')
        ->assertSuccessful()
        ->assertJsonPath('data.global_packages.0.code', 'carot-teamobi-100k')
        ->assertJsonPath('data.global_packages.0.denomination', 100000)
        ->assertJsonPath('data.global_packages.0.provider_id', $provider->id)
        ->assertJsonPath('data.global_packages.0.provider_price', 70000)
        ->assertJsonMissingPath('data.global_packages.0.level_prices');

    $mapping->refresh();

    expect($mapping->status)->toBe('active')
        ->and($mapping->provider_id)->toBe($provider->id)
        ->and($mapping->providerServiceCode())->toBe('nro')
        ->and($mapping->carot_amount)->toBe(195)
        ->and(data_get($mapping->metadata, 'global_receives.0.code'))->toBe('NX')
        ->and((int) $mapping->provider_price)->toBe(70000)
        ->and((int) $mapping->original_price)->toBe(100000)
        ->and((float) $mapping->discount_percent)->toBe(10.0)
        ->and($mapping->max_quantity)->toBe(10);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee('data-package-button="'.$mapping->id.'"', false)
        ->assertSee('-10%');
});

it('switches an existing game to global mode and builds denomination mappings automatically', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['package_mode' => 'custom']);
    $customPackage = TopupPackage::factory()->for($game)->create([
        'global_topup_package_id' => null,
        'status' => 'active',
    ]);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'provider_id' => null,
        'denomination' => 100000,
        'status' => 'active',
    ]);
    $this->actingAs($admin)->patchJson("/api/admin-api/games/{$game->id}", [
        'name' => $game->name,
        'slug' => $game->slug,
        'short_name' => $game->short_name,
        'reward_label' => $game->reward_label,
        'package_mode' => 'global',
        'status' => $game->status,
        'sort_order' => $game->sort_order,
        'metadata' => $game->metadata,
        'checkout_fields' => $game->checkoutFields(),
    ])->assertSuccessful();

    $mapping = TopupPackage::query()
        ->whereBelongsTo($game)
        ->whereBelongsTo($globalPackage, 'globalTopupPackage')
        ->firstOrFail();

    expect($game->refresh()->package_mode)->toBe('global')
        ->and($customPackage->refresh()->status)->toBe('inactive')
        ->and($mapping->denomination)->toBe(100000)
        ->and($mapping->status)->toBe('inactive');

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-rewards/{$game->id}", [
        'packages' => [[
            'denomination' => $globalPackage->denomination,
            'receives' => [[
                'code' => 'ITEM',
                'label' => 'Vật phẩm',
                'base_amount' => 100,
                'reward_x2_amount' => null,
                'reward_x3_amount' => null,
                'first_topup_reward_amount' => null,
            ]],
        ]],
    ])->assertSuccessful();

    expect($mapping->refresh()->status)->toBe('active');
});

it('uses one global price across games while custom games stay independent', function () {
    $globalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Carot Teamobi 100K',
        'denomination' => 100000,
        'provider_price' => 85000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $globalGames = Game::factory()->count(2)->create([
        'package_mode' => 'global',
    ]);
    $globalGames->each(fn (Game $game) => GlobalTopupPackageGameSetting::factory()->for($game)->create([
        'denomination' => $globalPackage->denomination,
    ]));
    $globalPackages = $globalGames->map(fn (Game $game) => TopupPackage::factory()->for($game)->create([
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 88000,
        'original_price' => 100000,
    ]));
    $service = app(TopupPackagePricingService::class);
    $firstGlobalPrice = $service->resolve($globalPackages->first());
    $secondGlobalPrice = $service->resolve($globalPackages->last());

    expect($firstGlobalPrice['package_source'])->toBe('global')
        ->and($firstGlobalPrice['retail_price'])->toBe(90000)
        ->and($firstGlobalPrice['final_price'])->toBe(90000)
        ->and($secondGlobalPrice['final_price'])->toBe(90000);

    $globalPackage->update(['price' => 95000]);
    expect($service->resolve($globalPackages->first()->refresh())['final_price'])->toBe(95000)
        ->and($service->resolve($globalPackages->last()->refresh())['final_price'])->toBe(95000);

    $customGame = Game::factory()->create(['package_mode' => 'custom']);
    $customPackage = TopupPackage::factory()->for($customGame)->create([
        'provider_price' => 80000,
        'price' => 100000,
        'original_price' => 110000,
    ]);
    $customPrice = $service->resolve($customPackage);
    expect($customPrice['package_source'])->toBe('custom')
        ->and($customPrice['final_price'])->toBe(100000);
});

it('uses every global package value and provider across games while keeping each game service code', function () {
    Mail::fake();
    Queue::fake();
    $the9p = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-the9p',
            'partner_key' => 'secret-the9p',
        ],
    ]);
    $wrongLocalProvider = TopupProvider::factory()->create([
        'slug' => 'accnrovn',
        'connection_config' => [
            'base_url' => 'https://accnro.vn/api/v1/partner/recharge',
            'partner_id' => 'partner-accnro',
            'secret_key' => 'secret-accnro',
        ],
    ]);
    $firstGame = Game::factory()->create(['package_mode' => 'global', 'provider_service_code' => 'nro']);
    $secondGame = Game::factory()->create(['package_mode' => 'global', 'provider_service_code' => 'avatar']);
    $firstServer = GameServer::factory()->for($firstGame)->create(['code' => '3']);
    $secondServer = GameServer::factory()->for($secondGame)->create(['code' => '7']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'provider_id' => $the9p->id,
        'name' => 'Global Carot 100K',
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 90000,
        'original_price' => 100000,
        'carot_amount' => 195,
        'reward_x2_amount' => 345,
        'reward_x3_amount' => 495,
        'first_topup_reward_amount' => 390,
        'bonus_text' => 'Thưởng Global',
        'min_quantity' => 1,
        'max_quantity' => 3,
    ]);
    GlobalTopupPackageGameSetting::factory()->for($globalPackage)->for($firstGame)->create([
        'provider_service_code' => 'nro',
        'receives' => [[
            'code' => 'NX',
            'label' => 'Ngọc xanh',
            'base_amount' => 150,
            'reward_x2_amount' => 195,
            'reward_x3_amount' => 250,
            'first_topup_reward_amount' => 300,
        ]],
    ]);
    GlobalTopupPackageGameSetting::factory()->for($globalPackage)->for($secondGame)->create([
        'provider_service_code' => 'avatar',
        'receives' => [
            [
                'code' => 'GM',
                'label' => 'Gem mở',
                'base_amount' => 100,
                'reward_x2_amount' => 150,
                'reward_x3_amount' => 200,
                'first_topup_reward_amount' => 200,
            ],
            [
                'code' => 'GK',
                'label' => 'Gem khóa',
                'base_amount' => 50,
                'reward_x2_amount' => 75,
                'reward_x3_amount' => 100,
                'first_topup_reward_amount' => 100,
            ],
        ],
    ]);
    app(GlobalTopupPackageSyncService::class)->sync($globalPackage);
    $firstPackage = TopupPackage::query()->whereBelongsTo($firstGame)->whereBelongsTo($globalPackage, 'globalTopupPackage')->firstOrFail();
    $secondPackage = TopupPackage::query()->whereBelongsTo($secondGame)->whereBelongsTo($globalPackage, 'globalTopupPackage')->firstOrFail();
    app(GlobalTopupPackageSyncService::class)->sync($globalPackage);

    foreach ([$firstPackage, $secondPackage] as $mapping) {
        $mapping->forceFill([
            'provider_id' => $wrongLocalProvider->id,
            'name' => 'Sai local',
            'denomination' => 50000,
            'carot_amount' => 1,
            'provider_price' => 89999,
            'price' => 89999,
            'original_price' => 89999,
            'min_quantity' => 3,
            'max_quantity' => 3,
        ])->save();
    }

    $user = User::factory()->create();
    $firstQuote = app(OrderPricingService::class)->quote(
        gameId: $firstGame->id,
        serverId: $firstServer->id,
        packageId: $firstPackage->id,
        quantity: 1,
        user: $user,
    );

    expect($firstQuote['package']->provider?->slug)->toBe('the9p')
        ->and($firstQuote['package']->providerServiceCode())->toBe('nro')
        ->and($firstQuote['server']->code)->toBe('3');

    $createOrder = fn (Game $game, GameServer $server, TopupPackage $package) => app(OrderService::class)->create([
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'global-player', 'game_character' => ''],
        'payment_method' => PaymentMethod::BankTransfer->value,
    ], $user, '127.0.0.1', 'Pest');

    $firstOrder = $createOrder($firstGame, $firstServer, $firstPackage);
    $secondOrder = $createOrder($secondGame, $secondServer, $secondPackage);

    expect($firstOrder->global_topup_package_id)->toBe($globalPackage->id)
        ->and($secondOrder->global_topup_package_id)->toBe($globalPackage->id)
        ->and($firstOrder->topup_package_id)->toBe($firstPackage->id)
        ->and($secondOrder->topup_package_id)->toBe($secondPackage->id)
        ->and($firstOrder->topup_provider_id)->toBe($the9p->id)
        ->and($secondOrder->topup_provider_id)->toBe($the9p->id)
        ->and(data_get($firstOrder->metadata, 'provider.service_code'))->toBe('nro')
        ->and(data_get($secondOrder->metadata, 'provider.service_code'))->toBe('avatar')
        ->and($firstOrder->package_name)->toBe('Global Carot 100K')
        ->and($secondOrder->package_name)->toBe('Global Carot 100K')
        ->and($firstOrder->denomination)->toBe(100000)
        ->and($firstOrder->carot_amount)->toBe(150)
        ->and(data_get($firstOrder->metadata, 'package.receives.0.code'))->toBe('NX')
        ->and(data_get($secondOrder->metadata, 'package.receives.0.code'))->toBe('GM')
        ->and(data_get($secondOrder->metadata, 'package.receives.1.code'))->toBe('GK')
        ->and((int) $firstOrder->provider_unit_cost)->toBe(70000)
        ->and((int) $firstOrder->sale_unit_price)->toBe(90000)
        ->and((int) $secondOrder->sale_unit_price)->toBe(90000);
});

it('prevents manual package mapping and management for global games', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $game = Game::factory()->create([
        'package_mode' => 'global',
        'provider_service_code' => 'nro',
    ]);
    $payload = [
        'game_id' => $game->id,
        'game_server_id' => null,
        'provider_id' => null,
        'name' => 'Gói riêng 100k',
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 88000,
        'original_price' => 100000,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_id');

    $customGame = Game::factory()->create(['package_mode' => 'custom']);

    $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', [
        ...$payload,
        'game_id' => $customGame->id,
        'global_topup_package_id' => $globalPackage->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('global_topup_package_id');

    GlobalTopupPackageGameSetting::factory()->for($globalPackage)->for($game)->create([
        'denomination' => $globalPackage->denomination,
    ]);
    $server = GameServer::factory()->for($game)->create();
    $legacyMapping = TopupPackage::factory()->for($game)->for($globalPackage)->create([
        'game_server_id' => $server->id,
        'status' => 'active',
    ]);

    app(GlobalTopupPackageSyncService::class)->syncPackageForGame($globalPackage, $game);
    $syncedPackage = TopupPackage::query()
        ->whereBelongsTo($game)
        ->whereBelongsTo($globalPackage, 'globalTopupPackage')
        ->whereNull('game_server_id')
        ->firstOrFail();

    expect($syncedPackage->status)->toBe('active')
        ->and($legacyMapping->refresh()->status)->toBe('inactive');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-packages?game_id='.$game->id)
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.data');

    $this->actingAs($admin)->patchJson("/api/admin-api/topup-packages/{$syncedPackage->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_id');

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-packages/{$syncedPackage->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('package');
});

it('rejects invalid global mappings and hides unavailable packages from the storefront', function () {
    $game = Game::factory()->create([
        'package_mode' => 'global',
    ]);
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'global_topup_package_id' => null,
        'name' => 'Gói chưa ánh xạ Global',
    ]);

    expect(fn () => app(OrderPricingService::class)->quote(
        gameId: $game->id,
        serverId: $server->id,
        packageId: $package->id,
        quantity: 1,
    ))->toThrow(ValidationException::class);

    $this->get('/')->assertSuccessful()->assertDontSee('Gói chưa ánh xạ Global');
});

it('snapshots the global package source while keeping the game package on new orders', function () {
    Mail::fake();
    Queue::fake();
    $globalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Carot Teamobi 100K',
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $game = Game::factory()->create([
        'package_mode' => 'global',
    ]);
    GlobalTopupPackageGameSetting::factory()->for($game)->create([
        'denomination' => $globalPackage->denomination,
    ]);
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 88000,
        'original_price' => 95000,
    ]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $order = app(OrderService::class)->create([
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'global-player', 'game_character' => ''],
        'payment_method' => PaymentMethod::Wallet->value,
    ], $user, '127.0.0.1', 'Pest');

    expect($order->package_source)->toBe('global')
        ->and($order->global_topup_package_id)->toBe($globalPackage->id)
        ->and($order->global_topup_package_name)->toBe('Carot Teamobi 100K')
        ->and($order->topup_package_id)->toBe($package->id)
        ->and((int) $order->unit_price)->toBe(100000)
        ->and((int) $order->sale_unit_price)->toBe(90000);
});
