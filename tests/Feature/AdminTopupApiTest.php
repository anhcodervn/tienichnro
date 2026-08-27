<?php

use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('topup admin api rejects guests and ordinary users', function (): void {
    $this->getJson('/api/admin-api/games')->assertUnauthorized();
    $this->getJson('/api/admin-api/topup-providers')->assertUnauthorized();
    $this->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [1]])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/games')
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/topup-providers')
        ->assertForbidden();
});

test('admin provider page refreshes and stores a valid low balance response', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create(['slug' => 'the9p']);
    Http::preventStrayRequests();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['balance' => 200, 'currency' => 'VND'],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [$provider->id]])
        ->assertOk()
        ->assertJsonPath('data.0.id', $provider->id)
        ->assertJsonPath('data.0.balance', 200)
        ->assertJsonPath('data.0.balance_currency', 'VND')
        ->assertJsonPath('data.0.balance_status', 'success')
        ->assertJsonPath('data.0.balance_error_code', null);

    expect($provider->refresh()->balance)->toBe(200)
        ->and($provider->balance_status)->toBe('success')
        ->and($provider->balance_checked_at)->not->toBeNull();
});

test('failed provider balance refresh keeps last balance and exposes a safe diagnostic', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'balance' => 500000,
        'balance_currency' => 'VND',
        'balance_status' => 'success',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response(['message' => 'secret-key must not leak'], 403),
    ]);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [$provider->id]])
        ->assertOk()
        ->assertJsonPath('data.0.balance', 500000)
        ->assertJsonPath('data.0.balance_status', 'failed')
        ->assertJsonPath('data.0.balance_error_code', 'authentication_failed');

    expect($response->getContent())->not->toContain('secret-key')
        ->and($provider->refresh()->balance)->toBe(500000)
        ->and($provider->balance_error_message)->toContain('IP whitelist');
});

test('catalog lists support server side search filters and pagination', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $targetGame = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'status' => 'active',
        'sort_order' => 1,
    ]);
    Game::factory()->inactive()->count(11)->create(['sort_order' => 2]);
    $targetServer = GameServer::factory()->for($targetGame)->create([
        'name' => 'Vũ trụ 1',
        'code' => 'NRO-01',
        'status' => 'active',
    ]);
    GameServer::factory()->create(['name' => 'Máy chủ khác']);
    $targetProvider = TopupProvider::factory()->create(['name' => 'The9p', 'slug' => 'the9p']);
    TopupProvider::factory()->create(['name' => 'Provider khác']);
    TopupPackage::factory()->for($targetGame)->create([
        'game_server_id' => $targetServer->id,
        'provider_id' => $targetProvider->id,
        'name' => 'Gói Ngọc Rồng 100k',
        'provider_price' => 81000,
        'original_price' => 100000,
        'price' => 85000,
        'status' => 'active',
    ]);
    TopupPackage::factory()->create(['name' => 'Gói game khác', 'status' => 'inactive']);

    $this->actingAs($admin)->getJson('/api/admin-api/games?status=inactive&per_page=10&page=2')
        ->assertOk()
        ->assertJsonPath('data.meta.current_page', 2)
        ->assertJsonPath('data.meta.per_page', 10)
        ->assertJsonPath('data.meta.total', 11)
        ->assertJsonCount(1, 'data.data');

    $this->actingAs($admin)->getJson('/api/admin-api/game-servers?search=NRO-01&game_id='.$targetGame->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $targetServer->id);

    $this->actingAs($admin)->getJson('/api/admin-api/topup-packages?search=Ngọc&game_id='.$targetGame->id.'&game_server_id='.$targetServer->id.'&provider_id='.$targetProvider->id.'&status=active&min_price=80000&max_price=90000')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.name', 'Gói Ngọc Rồng 100k');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-providers?search=the9p')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $targetProvider->id);
});

test('catalog list endpoints reject invalid filters', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->getJson('/api/admin-api/games?status=archived&per_page=11')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'per_page']);

    $this->actingAs($admin)->getJson('/api/admin-api/game-servers?game_id=999999')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_id');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-packages?min_price=90000&max_price=80000')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('max_price');

    $this->actingAs($admin)->getJson('/api/admin-api/topup-providers?per_page=5')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});

test('admin manages encrypted provider connection config without leaking secrets', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $connectionConfig = [
        'base_url' => 'https://api.provider.example/v1',
        'api_key' => 'public-key-123',
        'api_secret' => 'very-secret-value',
        'timeout' => 30,
        'balance_warning_threshold' => 1000000,
    ];

    $created = $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Provider Carot',
        'slug' => 'provider-carot',
        'connection_config' => $connectionConfig,
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Provider Carot')
        ->assertJsonPath('data.slug', 'provider-carot')
        ->assertJsonPath('data.has_connection_config', true);

    expect($created->json('data'))->not->toHaveKey('connection_config');

    $provider = TopupProvider::query()->findOrFail($created->json('data.id'));
    $rawConfig = DB::table($provider->getTable())->where('id', $provider->id)->value('connection_config');

    expect($provider->connection_config)->toBe($connectionConfig)
        ->and($provider->toArray())->not->toHaveKey('connection_config')
        ->and($rawConfig)->not->toContain('very-secret-value')
        ->and($rawConfig)->not->toContain('api.provider.example');

    $shown = $this->actingAs($admin)->getJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertOk()
        ->assertJsonPath('data.connection_config.base_url', 'https://api.provider.example/v1')
        ->assertJsonPath('data.connection_config.api_key', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.api_secret', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.timeout', 30)
        ->assertJsonPath('data.connection_config.balance_warning_threshold', 1000000);

    expect($shown->getContent())->not->toContain('public-key-123')
        ->not->toContain('very-secret-value');

    $this->actingAs($admin)->putJson("/api/admin-api/topup-providers/{$provider->id}", [
        'name' => 'Provider Carot mới',
        'slug' => 'provider-carot',
        'connection_config' => [
            'base_url' => 'https://new.provider.example/v2',
            'api_key' => TopupProvider::SECRET_MASK,
            'api_secret' => TopupProvider::SECRET_MASK,
            'timeout' => 45,
            'balance_warning_threshold' => 2000000,
        ],
    ])->assertOk();

    expect($provider->refresh()->connection_config)->toBe([
        'base_url' => 'https://new.provider.example/v2',
        'api_key' => 'public-key-123',
        'api_secret' => 'very-secret-value',
        'timeout' => 45,
        'balance_warning_threshold' => 2000000,
    ]);

    $this->actingAs($admin)->patchJson("/api/admin-api/topup-providers/{$provider->id}", [
        'name' => 'Provider chỉ đổi tên',
        'slug' => 'provider-carot',
    ])->assertOk();

    expect($provider->refresh()->connection_config['api_secret'])->toBe('very-secret-value');

    $auditJson = AdminAuditLog::query()
        ->where('subject_type', TopupProvider::class)
        ->get(['old_values', 'new_values'])
        ->toJson();

    expect($auditJson)->not->toContain('public-key-123')
        ->not->toContain('very-secret-value')
        ->not->toContain((string) $rawConfig);
});

test('provider validation rejects unsafe connection config and duplicate slugs', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    TopupProvider::factory()->create(['slug' => 'duplicate-provider']);

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Trùng slug',
        'slug' => 'duplicate-provider',
        'connection_config' => ['base_url' => 'https://provider.example'],
    ])->assertUnprocessable()->assertJsonValidationErrors('slug');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Private provider',
        'slug' => 'private-provider',
        'connection_config' => ['base_url' => 'http://127.0.0.1/admin'],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Sai cấu trúc',
        'slug' => 'invalid-provider',
        'connection_config' => ['first', 'second'],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');

    $this->actingAs($admin)->postJson('/api/admin-api/topup-providers', [
        'name' => 'Sai ngưỡng cảnh báo',
        'slug' => 'invalid-balance-threshold',
        'connection_config' => [
            'base_url' => 'https://provider.example',
            'balance_warning_threshold' => -1,
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('connection_config');
});

test('assigned provider cannot be deleted until packages are detached', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create();
    $package = TopupPackage::factory()->create(['provider_id' => $provider->id]);

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');

    expect($provider->fresh())->not->toBeNull()
        ->and($package->refresh()->provider_id)->toBe($provider->id);

    $package->forceFill(['provider_id' => null])->save();

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertOk();

    expect($provider->fresh())->toBeNull();
});

test('admin can create update and delete an empty game with audit logs', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $created = $this->actingAs($admin)->postJson('/api/admin-api/games', [
        'name' => 'Ninja School Online',
        'slug' => 'ninja-school-online',
        'reward_label' => 'Xu',
        'checkout_fields' => [
            ['key' => 'account_id', 'label' => 'ID tài khoản', 'placeholder' => 'Nhập ID', 'required' => true],
            ['key' => 'character_name', 'label' => 'Tên nhân vật', 'placeholder' => null, 'required' => false],
        ],
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated();

    $game = Game::query()->findOrFail($created->json('data.id'));

    expect($game->reward_label)->toBe('Xu')
        ->and($game->checkout_fields)->toHaveCount(2)
        ->and($game->checkout_fields[0]['key'])->toBe('account_id');

    $this->actingAs($admin)->patchJson("/api/admin-api/games/{$game->id}", [
        'name' => 'Ninja School',
        'slug' => $game->slug,
        'reward_label' => 'Xu',
        'checkout_fields' => $game->checkout_fields,
        'status' => $game->status,
        'sort_order' => $game->sort_order,
    ])->assertOk();

    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertOk();

    expect($game->fresh())->toBeNull()
        ->and(AdminAuditLog::query()->where('admin_id', $admin->id)->count())->toBe(3);
});

test('game checkout field schema rejects unsafe and ambiguous definitions', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $basePayload = [
        'name' => 'Game schema test',
        'slug' => 'game-schema-test',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => false],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'email', 'label' => 'Ghi đè email', 'placeholder' => '', 'required' => true],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.key');

    $this->actingAs($admin)->postJson('/api/admin-api/games', [
        ...$basePayload,
        'checkout_fields' => [
            ['key' => 'amount', 'label' => 'Ghi đè số lượng API', 'placeholder' => '', 'required' => true],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('checkout_fields.0.key');
});

test('admin stores provider field names directly in the game checkout schema', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = [
        'name' => 'Game direct provider fields',
        'slug' => 'game-direct-provider-fields',
        'reward_label' => 'Xu',
        'status' => 'active',
        'sort_order' => 1,
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Email', 'placeholder' => '', 'required' => true],
            ['key' => 'character', 'label' => 'Nhân vật', 'placeholder' => '', 'required' => false],
        ],
        'metadata' => [],
    ];

    $created = $this->actingAs($admin)
        ->postJson('/api/admin-api/games', $payload)
        ->assertCreated()
        ->assertJsonPath('data.checkout_fields.0.key', 'account')
        ->assertJsonPath('data.checkout_fields.1.key', 'character');
    $game = Game::query()->findOrFail($created->json('data.id'));

    $this->actingAs($admin)->postJson('/api/admin-api/game-servers', [
        'game_id' => $game->id,
        'name' => 'Server 16',
        'code' => '16',
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.code', '16');

    expect($game->metadata)->toBe([])
        ->and($game->checkout_fields[0]['key'])->toBe('account');
});

test('catalog deletion requires child records to be removed first', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create(['game_server_id' => $server->id]);

    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game');

    $this->actingAs($admin)->deleteJson("/api/admin-api/game-servers/{$server->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('server');

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-packages/{$package->id}")
        ->assertOk();
    $this->actingAs($admin)->deleteJson("/api/admin-api/game-servers/{$server->id}")
        ->assertOk();
    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertOk();

    expect($package->fresh())->toBeNull()
        ->and($server->fresh())->toBeNull()
        ->and($game->fresh())->toBeNull()
        ->and(AdminAuditLog::query()->where('action', 'deleted')->count())->toBe(3);
});

test('catalog records with order history cannot be deleted', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create(['game_server_id' => $server->id]);
    Order::factory()->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'package_name' => $package->name,
    ]);

    $this->actingAs($admin)->deleteJson("/api/admin-api/topup-packages/{$package->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('package');
    $this->actingAs($admin)->deleteJson("/api/admin-api/game-servers/{$server->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('server');
    $this->actingAs($admin)->deleteJson("/api/admin-api/games/{$game->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game');

    expect($package->fresh())->not->toBeNull()
        ->and($server->fresh())->not->toBeNull()
        ->and($game->fresh())->not->toBeNull();
});

test('admin package pricing stores provider cost and calculates discount on the server', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $provider = TopupProvider::factory()->create();

    $created = $this->actingAs($admin)->postJson('/api/admin-api/topup-packages', [
        'game_id' => $game->id,
        'game_server_id' => null,
        'provider_id' => $provider->id,
        'provider_service_code' => 'NRO',
        'name' => 'Gói 10.000đ',
        'denomination' => 10000,
        'carot_amount' => 13,
        'reward_x2_amount' => 22,
        'reward_x3_amount' => 32,
        'first_topup_reward_amount' => 26,
        'provider_price' => 8100,
        'original_price' => 10000,
        'price' => 8500,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.provider_price', '8100.00')
        ->assertJsonPath('data.original_price', '10000.00')
        ->assertJsonPath('data.price', '8500.00')
        ->assertJsonPath('data.discount_percent', '15.00')
        ->assertJsonPath('data.carot_amount', 13)
        ->assertJsonPath('data.reward_x2_amount', 22)
        ->assertJsonPath('data.reward_x3_amount', 32)
        ->assertJsonPath('data.first_topup_reward_amount', 26)
        ->assertJsonPath('data.provider_id', $provider->id)
        ->assertJsonPath('data.provider_service_code', 'NRO')
        ->assertJsonPath('data.provider_name', $provider->name);

    $package = TopupPackage::query()->findOrFail($created->json('data.id'));

    expect($package->provider_price)->toBe('8100.00')
        ->and($package->original_price)->toBe('10000.00')
        ->and($package->price)->toBe('8500.00')
        ->and($package->carot_amount)->toBe(13)
        ->and($package->reward_x2_amount)->toBe(22)
        ->and($package->reward_x3_amount)->toBe(32)
        ->and($package->first_topup_reward_amount)->toBe(26)
        ->and($package->provider_service_code)->toBe('NRO')
        ->and($package->discount_percent)->toBe('15.00');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [
            'game_id' => $game->id,
            'name' => 'Gói can thiệp chiết khấu',
            'provider_price' => 8100,
            'original_price' => 10000,
            'price' => 8500,
            'discount_percent' => 99,
            'min_quantity' => 1,
            'max_quantity' => 10,
            'status' => 'active',
            'sort_order' => 2,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('discount_percent');
});

test('admin package pricing rejects provider cost above sale price and original price below it', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $otherGameServer = GameServer::factory()->create();
    $payload = [
        'game_id' => $game->id,
        'game_server_id' => null,
        'name' => 'Gói 10.000đ',
        'carot_amount' => 1,
        'provider_price' => 8100,
        'original_price' => 10000,
        'price' => 8500,
        'min_quantity' => 1,
        'max_quantity' => 10,
        'status' => 'active',
        'sort_order' => 1,
    ];

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'provider_price' => 9000])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider_price');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'original_price' => 8100])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('original_price');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'reward_x2_amount' => -1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reward_x2_amount');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-packages', [...$payload, 'game_server_id' => $otherGameServer->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('game_server_id');
});
