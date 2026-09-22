<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Mail\Orders\OrderCreatedMail;
use App\Models\ApiKey;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

function topupApiCredentials(User $user, array $permissions = ['balance:read', 'catalog:read', 'orders:create', 'orders:read'], array $overrides = []): array
{
    $secret = 'ncs_'.Str::random(64);
    $apiKey = ApiKey::factory()->for($user)->create([
        'permissions' => $permissions,
        'api_secret_hash' => Hash::make($secret),
        ...$overrides,
    ]);

    return [
        'X-API-KEY' => $apiKey->api_key,
        'X-API-SECRET' => $secret,
    ];
}

function topupApiCatalog(array $packageAttributes = [], array $gameAttributes = []): array
{
    $game = Game::factory()->create($gameAttributes);
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
        ...$packageAttributes,
    ]);

    return [$game, $server, $package];
}

function topupApiPayload(Game $game, GameServer $server, TopupPackage $package, array $overrides = []): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'game' => $game->id,
        'server' => $server->id,
        'price' => (int) $package->denomination,
        'payload' => [
            ['game_account' => 'player-one', 'amount' => 2],
        ],
        ...$overrides,
    ];
}

test('public topup api requires api key and secret with the correct permission', function (): void {
    $this->getJson('/api/v1/balance')->assertUnauthorized();

    $user = User::factory()->create();
    $credentials = topupApiCredentials($user, ['orders:read']);

    $this->withHeaders($credentials)->getJson('/api/v1/balance')->assertForbidden();
});

test('api rejects an invalid secret and expired or revoked keys', function (): void {
    $user = User::factory()->create();
    $credentials = topupApiCredentials($user);

    $this->withHeaders([
        ...$credentials,
        'X-API-SECRET' => 'wrong-secret',
    ])->getJson('/api/v1/balance')
        ->assertUnauthorized()
        ->assertExactJson([
            'status' => false,
            'message' => 'API key hoặc API secret không hợp lệ.',
        ]);

    ApiKey::query()->where('api_key', $credentials['X-API-KEY'])->update(['status' => 'revoked']);
    $this->withHeaders($credentials)
        ->getJson('/api/v1/balance')
        ->assertUnauthorized();

    $expiredCredentials = topupApiCredentials($user, overrides: ['expired_at' => now()->subMinute()]);
    $this->withHeaders($expiredCredentials)
        ->getJson('/api/v1/balance')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'API key đã hết hạn.');
});

test('balance endpoint returns only the useful wallet fields', function (): void {
    $user = User::factory()->create();
    $user->wallet()->update([
        'balance' => 275000,
        'hold_balance' => 12000,
        'total_recharge' => 999000,
    ]);

    $this->withHeaders(topupApiCredentials($user))
        ->getJson('/api/v1/balance')
        ->assertOk()
        ->assertExactJson([
            'status' => true,
            'data' => [
                'balance' => 275000,
                'currency' => 'VND',
            ],
        ]);
});

test('catalog returns active games servers packages sale prices and game payload fields', function (): void {
    [$game, $server, $package] = topupApiCatalog(gameAttributes: [
        'min_quantity' => 2,
        'max_quantity' => 4,
    ]);
    $game->update(['checkout_fields' => [
        ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => 'Nhập tài khoản', 'required' => true],
    ]]);
    Game::factory()->inactive()->create();
    GameServer::factory()->for($game)->create(['status' => 'inactive']);
    TopupPackage::factory()->for($game)->inactive()->create(['game_server_id' => $server->id]);
    $user = User::factory()->create();

    $this->withHeaders(topupApiCredentials($user))
        ->getJson('/api/v1/catalog')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $game->id)
        ->assertJsonPath('data.0.min_amount', 2)
        ->assertJsonPath('data.0.max_amount', 4)
        ->assertJsonPath('data.0.servers.0.id', $server->id)
        ->assertJsonPath('data.0.payload_fields.0.key', 'account')
        ->assertJsonPath('data.0.packages.0.id', $package->id)
        ->assertJsonPath('data.0.packages.0.price', 100000)
        ->assertJsonPath('data.0.packages.0.sale_price', 90000)
        ->assertJsonPath('data.0.packages.0.min_amount', 2)
        ->assertJsonPath('data.0.packages.0.max_amount', 4)
        ->assertJsonMissingPath('data.0.packages.0.server_id')
        ->assertJsonMissingPath('data.0.packages.0.provider_id')
        ->assertJsonMissingPath('data.0.packages.0.provider_price');
});

test('api creates a multi recipient wallet order with per-recipient amounts and server-side pricing', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);
    $payload = topupApiPayload($game, $server, $package, [
        'payload' => [
            ['game_account' => 'Player-One', 'amount' => 2],
            ['game_account' => 'PLAYER-TWO', 'amount' => 1],
        ],
    ]);

    $response = $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.request_id', $payload['request_id'])
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.topup_id', null)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.total', 270000)
        ->assertJsonPath('data.currency', 'VND')
        ->assertJsonCount(2, 'data.payload')
        ->assertJsonPath('data.payload.0.game_account', 'player-one')
        ->assertJsonPath('data.payload.0.amount', 2)
        ->assertJsonPath('data.payload.1.game_account', 'player-two')
        ->assertJsonPath('data.payload.1.amount', 1)
        ->assertJsonMissingPath('data.amount')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.provider')
        ->assertJsonMissingPath('data.provider_reference')
        ->assertJsonMissingPath('data.metadata');

    $order = Order::query()->sole();
    expect($response->json('data.order_id'))->toBe($order->code)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->purchase_mode)->toBe('bulk')
        ->and($order->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->recipients()->count())->toBe(2)
        ->and($order->game_account)->toBe('player-one')
        ->and($order->recipients()->orderBy('position')->get()->pluck('recipient_data')->all())->toBe([
            ['game_account' => 'player-one', 'game_character' => ''],
            ['game_account' => 'player-two', 'game_character' => ''],
        ])
        ->and((int) $user->wallet()->value('balance'))->toBe(230000)
        ->and(WalletTransaction::query()->count())->toBe(1);

    Queue::assertPushed(ProcessTopupOrder::class, 1);
    Mail::assertQueued(OrderCreatedMail::class, 1);
});

test('api accepts the maximum amount for one recipient', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 2000000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'payload' => [['game_account' => 'player-one', 'amount' => 10]],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.payload.0.amount', 10)
        ->assertJsonPath('data.total', 900000);

    expect(Order::query()->sole()->recipients()->sole()->quantity)->toBe(10);
});

test('api rejects more than ten cards for a single recipient', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 2000000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'payload' => [['game_account' => 'player-one', 'amount' => 11]],
        ]))
        ->assertUnprocessable();

    expect(Order::query()->count())->toBe(0);
});

test('api rejects the removed top-level amount field', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, ['amount' => 1]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Không gửi amount ở cấp ngoài; hãy đặt amount trong từng phần tử payload.');

    expect(Order::query()->count())->toBe(0);
});

test('api requires an amount in every payload item and limits the recipient list', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);
    $credentials = topupApiCredentials($user);

    $this->withHeaders($credentials)
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'payload' => [['game_account' => 'player-one']],
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mỗi tài khoản trong payload phải có amount.');

    $this->app['auth']->forgetGuards();

    $this->withHeaders($credentials)
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'payload' => collect(range(1, 101))
                ->map(fn (int $index): array => ['game_account' => "player-{$index}", 'amount' => 1])
                ->all(),
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Mỗi đơn chỉ được có tối đa 100 tài khoản nhận.');

    expect(Order::query()->count())->toBe(0);
});

test('repeating request id returns the same order without a second debit or job', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);
    $credentials = topupApiCredentials($user);
    $payload = topupApiPayload($game, $server, $package);

    $firstOrderId = $this->withHeaders($credentials)
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated()
        ->json('data.order_id');
    $package->update(['status' => 'inactive']);

    $this->withHeaders($credentials)
        ->postJson('/api/v1/orders', $payload)
        ->assertOk()
        ->assertJsonPath('data.order_id', $firstOrderId);

    expect(Order::query()->count())->toBe(1)
        ->and(WalletTransaction::query()->count())->toBe(1)
        ->and((int) $user->wallet()->value('balance'))->toBe(320000);
    Queue::assertPushed(ProcessTopupOrder::class, 1);
    Mail::assertQueued(OrderCreatedMail::class, 1);
});

test('insufficient balance returns useful amounts and creates nothing', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 100000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package))
        ->assertUnprocessable()
        ->assertExactJson([
            'status' => false,
            'message' => 'Số dư ví không đủ để tạo đơn nạp.',
            'data' => [
                'balance' => 100000,
                'required_amount' => 180000,
                'currency' => 'VND',
            ],
        ]);

    expect(Order::query()->count())->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(0)
        ->and((int) $user->wallet()->value('balance'))->toBe(100000);
    Queue::assertNothingPushed();
    Mail::assertNothingQueued();
});

test('order status is owner scoped and does not expose provider internals', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'package_name' => $package->name,
        'quantity' => 2,
        'total_amount' => 180000,
        'payment_method' => PaymentMethod::Wallet,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
        'provider_reference' => 'PRIVATE-PROVIDER-REF',
        'metadata' => ['provider' => ['api_secret' => 'PRIVATE-SECRET']],
    ]);
    OrderRecipient::factory()->for($order)->create([
        'recipient_data' => ['game_account' => 'player-one'],
        'quantity' => 2,
        'status' => 'processing',
        'provider_reference' => 'PRIVATE-RECIPIENT-REF',
        'provider_response' => ['secret' => 'PRIVATE-RESPONSE'],
    ]);

    $this->withHeaders(topupApiCredentials($otherUser))
        ->getJson("/api/v1/orders/{$order->code}")
        ->assertNotFound()
        ->assertExactJson([
            'status' => false,
            'message' => 'Không tìm thấy đơn nạp.',
        ]);

    $this->app['auth']->forgetGuards();

    $this->withHeaders(topupApiCredentials($owner))
        ->getJson("/api/v1/orders/{$order->code}")
        ->assertOk()
        ->assertJsonPath('data.order_id', $order->code)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.payload.0.game_account', 'player-one')
        ->assertJsonPath('data.payload.0.amount', 2)
        ->assertJsonMissing(['PRIVATE-PROVIDER-REF'])
        ->assertJsonMissing(['PRIVATE-RECIPIENT-REF'])
        ->assertJsonMissing(['PRIVATE-SECRET'])
        ->assertJsonMissing(['PRIVATE-RESPONSE']);
});

test('order api rejects inactive users and request id collisions without leaking an order', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $requestId = (string) Str::uuid();
    Order::factory()->for($owner)->create(['idempotency_key' => $requestId]);

    $this->withHeaders(topupApiCredentials($otherUser))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'request_id' => $requestId,
        ]))
        ->assertConflict()
        ->assertJson([
            'status' => false,
        ]);

    $inactiveUser = User::factory()->create(['status' => 'inactive']);
    $this->app['auth']->forgetGuards();

    $this->withHeaders(topupApiCredentials($inactiveUser))
        ->getJson('/api/v1/balance')
        ->assertForbidden()
        ->assertExactJson([
            'status' => false,
            'message' => 'Tài khoản không thể sử dụng API.',
        ]);
});

test('create order validates request id and configured payload fields', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $game->update(['checkout_fields' => [
        ['key' => 'username', 'label' => 'Tên tài khoản', 'placeholder' => '', 'required' => true],
        ['key' => 'character', 'label' => 'Nhân vật', 'placeholder' => '', 'required' => true],
    ]]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'request_id' => 'not-a-uuid',
        ]))
        ->assertUnprocessable()
        ->assertJson([
            'status' => false,
            'message' => 'request_id phải là UUID hợp lệ.',
        ]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package, [
            'payload' => [['username' => 'player-one', 'amount' => 1]],
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Nhân vật ở dòng 1 không được để trống.');

    expect(Order::query()->count())->toBe(0);
});

test('create order rejects an ambiguous denomination instead of choosing a provider silently', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'denomination' => $package->denomination,
        'price' => 85000,
    ]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/orders', topupApiPayload($game, $server, $package))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Có nhiều gói nạp trùng game và mệnh giá. Vui lòng liên hệ quản trị viên.');

    expect(Order::query()->count())->toBe(0);
});
