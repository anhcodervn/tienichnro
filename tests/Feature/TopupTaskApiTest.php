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

function topupApiCredentials(User $user, array $permissions = ['balance:read', 'tasks:create', 'tasks:read'], array $overrides = []): array
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

function topupApiCatalog(array $packageAttributes = []): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'price' => 90000,
        'original_price' => 100000,
        'min_quantity' => 1,
        'max_quantity' => 10,
        ...$packageAttributes,
    ]);

    return [$game, $server, $package];
}

function topupApiPayload(Game $game, GameServer $server, TopupPackage $package, array $overrides = []): array
{
    return [
        'request_id' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'recipients' => [[
            'data' => ['game_account' => 'player-one'],
            'quantity' => 2,
        ]],
        ...$overrides,
    ];
}

test('public task api requires api key and secret with the correct permission', function (): void {
    $this->getJson('/api/v1/balance')->assertUnauthorized();

    $user = User::factory()->create();
    $credentials = topupApiCredentials($user, ['tasks:read']);

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

test('api creates a wallet task for one or many recipients with server-side pricing', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);
    $payload = topupApiPayload($game, $server, $package, [
        'price' => 1,
        'recipients' => [
            ['data' => ['game_account' => 'player-one'], 'quantity' => 2],
            ['data' => ['game_account' => 'player-two'], 'quantity' => 1],
        ],
    ]);

    $response = $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/tasks', $payload)
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.request_id', $payload['request_id'])
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.quantity', 3)
        ->assertJsonPath('data.amount', 270000)
        ->assertJsonPath('data.currency', 'VND')
        ->assertJsonCount(2, 'data.recipients')
        ->assertJsonMissingPath('data.email')
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.provider')
        ->assertJsonMissingPath('data.provider_reference')
        ->assertJsonMissingPath('data.metadata');

    $order = Order::query()->sole();
    expect($response->json('data.task_id'))->toBe($order->code)
        ->and($order->user_id)->toBe($user->id)
        ->and($order->purchase_mode)->toBe('bulk')
        ->and($order->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->recipients()->count())->toBe(2)
        ->and((int) $user->wallet()->value('balance'))->toBe(230000)
        ->and(WalletTransaction::query()->count())->toBe(1);

    Queue::assertPushed(ProcessTopupOrder::class, 1);
    Mail::assertQueued(OrderCreatedMail::class, 1);
});

test('api allows more than ten cards in total when each recipient has at most ten', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 2000000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package, [
            'recipients' => [
                ['data' => ['game_account' => 'player-one'], 'quantity' => 6],
                ['data' => ['game_account' => 'player-two'], 'quantity' => 5],
            ],
        ]))
        ->assertCreated()
        ->assertJsonPath('data.quantity', 11)
        ->assertJsonPath('data.amount', 990000);

    expect(Order::query()->sole()->recipients()->orderBy('position')->pluck('quantity')->all())->toBe([6, 5]);
});

test('api rejects more than ten cards for a single recipient', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 2000000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package, [
            'recipients' => [
                ['data' => ['game_account' => 'player-one'], 'quantity' => 11],
            ],
        ]))
        ->assertUnprocessable();

    expect(Order::query()->count())->toBe(0);
});

test('repeating request id returns the same task without a second debit or job', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);
    $credentials = topupApiCredentials($user);
    $payload = topupApiPayload($game, $server, $package);

    $firstTaskId = $this->withHeaders($credentials)
        ->postJson('/api/v1/tasks', $payload)
        ->assertCreated()
        ->json('data.task_id');

    $this->withHeaders($credentials)
        ->postJson('/api/v1/tasks', $payload)
        ->assertOk()
        ->assertJsonPath('data.task_id', $firstTaskId);

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
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package))
        ->assertUnprocessable()
        ->assertExactJson([
            'status' => false,
            'message' => 'Số dư ví không đủ để tạo task.',
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

test('task status is owner scoped and does not expose provider internals', function (): void {
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
        ->getJson("/api/v1/tasks/{$order->code}")
        ->assertNotFound()
        ->assertExactJson([
            'status' => false,
            'message' => 'Không tìm thấy task.',
        ]);

    $this->app['auth']->forgetGuards();

    $this->withHeaders(topupApiCredentials($owner))
        ->getJson("/api/v1/tasks/{$order->code}")
        ->assertOk()
        ->assertJsonPath('data.task_id', $order->code)
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.recipients.0.data.game_account', 'player-one')
        ->assertJsonMissing(['PRIVATE-PROVIDER-REF'])
        ->assertJsonMissing(['PRIVATE-RECIPIENT-REF'])
        ->assertJsonMissing(['PRIVATE-SECRET'])
        ->assertJsonMissing(['PRIVATE-RESPONSE']);
});

test('task api rejects inactive users and request id collisions without leaking an order', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $requestId = (string) Str::uuid();
    Order::factory()->for($owner)->create(['idempotency_key' => $requestId]);

    $this->withHeaders(topupApiCredentials($otherUser))
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package, [
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

test('create task validates request id and configured recipient fields', function (): void {
    [$game, $server, $package] = topupApiCatalog();
    $game->update(['checkout_fields' => [
        ['key' => 'username', 'label' => 'Tên tài khoản', 'placeholder' => '', 'required' => true],
        ['key' => 'character', 'label' => 'Nhân vật', 'placeholder' => '', 'required' => true],
    ]]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 500000]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package, [
            'request_id' => 'not-a-uuid',
        ]))
        ->assertUnprocessable()
        ->assertJson([
            'status' => false,
            'message' => 'request_id phải là UUID hợp lệ.',
        ]);

    $this->withHeaders(topupApiCredentials($user))
        ->postJson('/api/v1/tasks', topupApiPayload($game, $server, $package, [
            'recipients' => [[
                'data' => ['username' => 'player-one'],
                'quantity' => 1,
            ]],
        ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Nhân vật ở dòng 1 không được để trống.');

    expect(Order::query()->count())->toBe(0);
});
