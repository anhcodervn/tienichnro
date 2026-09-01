<?php

use App\Enums\PaymentMethod;
use App\Features\MemberLevel\Services\MemberLevelPriceService;
use App\Features\Topup\Services\OrderPricingService;
use App\Features\Topup\Services\OrderService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GlobalTopupPackage;
use App\Models\MemberLevel;
use App\Models\MemberLevelAccount;
use App\Models\MemberLevelGlobalPackagePrice;
use App\Models\MemberLevelPackagePrice;
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
    $level = MemberLevel::factory()->create([
        'code' => 'global-agency',
        'rank' => 10,
        'lifetime_threshold' => 10000000,
    ]);

    $packageResponse = $this->actingAs($admin)->postJson('/api/admin-api/global-topup-packages', [
        'name' => 'Carot Teamobi 100K',
        'code' => 'carot-teamobi-100k',
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
        'description' => 'Dùng chung cho các game Teamobi',
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated();
    $globalPackage = GlobalTopupPackage::query()->findOrFail($packageResponse->json('data.global_package.id'));

    $this->actingAs($admin)->putJson("/api/admin-api/global-topup-packages/{$globalPackage->id}/levels/{$level->id}", [
        'pricing_mode' => 'discount',
        'discount_basis_points' => 500,
        'fixed_price' => null,
        'minimum_profit' => 1000,
        'is_active' => true,
    ])->assertSuccessful();

    $this->actingAs($admin)->getJson('/api/admin-api/global-topup-packages')
        ->assertSuccessful()
        ->assertJsonPath('data.global_packages.0.code', 'carot-teamobi-100k')
        ->assertJsonPath('data.global_packages.0.denomination', 100000)
        ->assertJsonPath('data.global_packages.0.level_prices.0.member_level_id', $level->id);
});

it('uses one global price and global level override across games while custom games stay independent', function () {
    $globalPackage = GlobalTopupPackage::factory()->create([
        'name' => 'Carot Teamobi 100K',
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $level = MemberLevel::factory()->create([
        'code' => 'global-level',
        'rank' => 10,
        'lifetime_threshold' => 10000000,
        'default_discount_bps' => 100,
        'minimum_profit' => 500,
    ]);
    $user = User::factory()->create();
    MemberLevelAccount::factory()->for($user)->create([
        'manual_level_id' => $level->id,
        'manual_level_expires_at' => now()->addMonth(),
    ]);
    MemberLevelGlobalPackagePrice::factory()->create([
        'member_level_id' => $level->id,
        'global_topup_package_id' => $globalPackage->id,
        'pricing_mode' => 'discount',
        'discount_basis_points' => 1000,
        'minimum_profit' => 1000,
    ]);

    $globalGames = Game::factory()->count(2)->create([
        'package_mode' => 'global',
    ]);
    $globalPackages = $globalGames->map(fn (Game $game) => TopupPackage::factory()->for($game)->create([
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 100000,
        'provider_price' => 70000,
        'price' => 88000,
        'original_price' => 100000,
    ]));
    MemberLevelPackagePrice::factory()->create([
        'member_level_id' => $level->id,
        'topup_package_id' => $globalPackages->first()->id,
        'pricing_mode' => 'fixed',
        'fixed_price' => 89000,
    ]);

    $service = app(MemberLevelPriceService::class);
    $firstGlobalPrice = $service->resolve($globalPackages->first(), $user);
    $secondGlobalPrice = $service->resolve($globalPackages->last(), $user);

    expect($firstGlobalPrice['package_source'])->toBe('global')
        ->and($firstGlobalPrice['retail_price'])->toBe(90000)
        ->and($firstGlobalPrice['final_price'])->toBe(81000)
        ->and($secondGlobalPrice['final_price'])->toBe(81000);

    $globalPackage->update(['price' => 95000]);
    expect($service->resolve($globalPackages->first()->refresh(), $user)['final_price'])->toBe(85500)
        ->and($service->resolve($globalPackages->last()->refresh(), $user)['final_price'])->toBe(85500);

    MemberLevelGlobalPackagePrice::query()
        ->where('member_level_id', $level->id)
        ->where('global_topup_package_id', $globalPackage->id)
        ->update(['pricing_mode' => 'fixed', 'discount_basis_points' => null, 'fixed_price' => 82000]);
    expect($service->resolve($globalPackages->first()->refresh(), $user)['final_price'])->toBe(82000)
        ->and($service->resolve($globalPackages->last()->refresh(), $user)['final_price'])->toBe(82000);

    $customGame = Game::factory()->create(['package_mode' => 'custom']);
    $customPackage = TopupPackage::factory()->for($customGame)->create([
        'provider_price' => 80000,
        'price' => 100000,
        'original_price' => 110000,
    ]);
    MemberLevelPackagePrice::factory()->create([
        'member_level_id' => $level->id,
        'topup_package_id' => $customPackage->id,
        'pricing_mode' => 'fixed',
        'fixed_price' => 90000,
    ]);

    $customPrice = $service->resolve($customPackage, $user);
    expect($customPrice['package_source'])->toBe('custom')
        ->and($customPrice['final_price'])->toBe(90000);
});

it('keeps provider routing and service codes separate for games sharing one global package', function () {
    Mail::fake();
    Queue::fake();
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $the9p = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-the9p',
            'partner_key' => 'secret-the9p',
        ],
    ]);
    $accNro = TopupProvider::factory()->create([
        'slug' => 'accnrovn',
        'connection_config' => [
            'base_url' => 'https://accnro.vn/api/v1/partner/recharge',
            'partner_id' => 'partner-accnro',
            'secret_key' => 'secret-accnro',
        ],
    ]);
    $firstGame = Game::factory()->create(['package_mode' => 'global']);
    $secondGame = Game::factory()->create(['package_mode' => 'global']);
    $firstServer = GameServer::factory()->for($firstGame)->create(['code' => '3']);
    $secondServer = GameServer::factory()->for($secondGame)->create(['code' => '7']);
    $firstPackage = TopupPackage::factory()->for($firstGame)->create([
        'game_server_id' => $firstServer->id,
        'global_topup_package_id' => $globalPackage->id,
        'provider_id' => $the9p->id,
        'provider_service_code' => 'nro',
        'denomination' => 100000,
        'provider_price' => 70000,
    ]);
    $secondPackage = TopupPackage::factory()->for($secondGame)->create([
        'game_server_id' => $secondServer->id,
        'global_topup_package_id' => $globalPackage->id,
        'provider_id' => $accNro->id,
        'provider_service_code' => 'avatar',
        'denomination' => 100000,
        'provider_price' => 75000,
    ]);
    $user = User::factory()->create();

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
        ->and($secondOrder->topup_provider_id)->toBe($accNro->id)
        ->and(data_get($firstOrder->metadata, 'provider.service_code'))->toBe('nro')
        ->and(data_get($secondOrder->metadata, 'provider.service_code'))->toBe('avatar')
        ->and((int) $firstOrder->sale_unit_price)->toBe(90000)
        ->and((int) $secondOrder->sale_unit_price)->toBe(90000);
});

it('validates game package mappings against the selected global package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $globalPackage = GlobalTopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
    ]);
    $game = Game::factory()->create([
        'package_mode' => 'global',
    ]);
    $payload = [
        'game_id' => $game->id,
        'game_server_id' => null,
        'provider_id' => null,
        'provider_service_code' => null,
        'name' => 'Gói Global 100k',
        'denomination' => 100000,
        'carot_amount' => 100,
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
        ->assertJsonValidationErrors('global_topup_package_id');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', [
        ...$payload,
        'global_topup_package_id' => $globalPackage->id,
        'denomination' => 50000,
    ])->assertUnprocessable()->assertJsonValidationErrors('denomination');
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
