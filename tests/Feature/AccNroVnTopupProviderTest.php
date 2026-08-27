<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Topup\Jobs\SyncTopupRecipientStatus;
use App\Features\Topup\Providers\AccNroVnTopupProvider;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

test('accnrovn normalizes supported credential key aliases', function (array $credentials): void {
    $provider = new TopupProvider;
    $provider->connection_config = $credentials;

    expect(app(AccNroVnTopupProvider::class)->configuration($provider))->toMatchArray([
        'partner_id' => 'pk_partner_123',
        'secret_key' => 'sk_secret_key',
    ]);
})->with([
    'documented keys' => [['partner_id' => 'pk_partner_123', 'secret_key' => 'sk_secret_key']],
    'stored keys with existing typo' => [['partner_key' => 'pk_partner_123', 'serect_key' => 'sk_secret_key']],
    'legacy partner key as secret' => [['partner_id' => 'pk_partner_123', 'partner_key' => 'sk_secret_key']],
]);

test('accnrovn normalizes configured operation endpoints to the api base url', function (string $baseUrl): void {
    $provider = new TopupProvider;
    $provider->connection_config = [
        'base_url' => $baseUrl,
        'partner_id' => 'pk_partner_123',
        'secret_key' => 'sk_secret_key',
    ];

    expect(app(AccNroVnTopupProvider::class)->configuration($provider)['base_url'])
        ->toBe('https://accnro.vn/api/v1/partner/recharge');
})->with([
    'api base url' => ['https://accnro.vn/api/v1/partner/recharge'],
    'create endpoint' => ['https://accnro.vn/api/v1/partner/recharge/create'],
    'query endpoint' => ['https://accnro.vn/api/v1/partner/recharge/query/'],
    'balance endpoint' => ['https://accnro.vn/api/v1/partner/recharge/balance'],
]);

test('admin masks accnrovn stored credential aliases', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'accnrovn',
        'connection_config' => accNroVnConnectionConfig(),
    ]);

    $response = $this->actingAs($admin)
        ->getJson("/api/admin-api/topup-providers/{$provider->id}")
        ->assertSuccessful()
        ->assertJsonPath('data.connection_config.partner_key', TopupProvider::SECRET_MASK)
        ->assertJsonPath('data.connection_config.serect_key', TopupProvider::SECRET_MASK);

    expect($response->getContent())
        ->not->toContain('pk_partner_123')
        ->not->toContain('sk_secret_key');

    $this->actingAs($admin)
        ->putJson("/api/admin-api/topup-providers/{$provider->id}", [
            'name' => $provider->name,
            'slug' => 'accnrovn',
            'connection_config' => $response->json('data.connection_config'),
        ])
        ->assertSuccessful();

    expect($provider->refresh()->connection_config)->toMatchArray([
        'partner_key' => 'pk_partner_123',
        'serect_key' => 'sk_secret_key',
    ]);
});

test('accnrovn creates an idempotent order with an hmac signature', function (): void {
    [$order, $recipient] = accNroVnOrderFixture();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-NRO-1001',
                'request_id' => $order->code.'-R001',
                'status' => 'pending',
                'status_code' => 'QUEUED',
                'balance' => 9_950_000,
            ],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->submit($recipient->id);

    $recipient->refresh();
    expect($recipient->provider_request_id)->toBe($order->code.'-R001')
        ->and($recipient->provider_reference)->toBe('ACC-NRO-1001')
        ->and($recipient->provider_status)->toBe('processing')
        ->and($recipient->provider_response['items'][1]['response'])->toMatchArray([
            'http_status' => 200,
            'provider_status' => 'pending',
            'provider_code' => 'QUEUED',
            'envelope_status' => 'success',
        ])
        ->and($recipient->provider_response['items'][1]['submission']['status'])->toBe('pending')
        ->and($recipient->provider_response['items'][1]['submission']['request'])->toBe([
            'method' => 'POST',
            'url' => 'https://accnro.vn/api/v1/partner/recharge/create',
            'payload' => [
                'partner_id' => 'pk_partner_123',
                'request_id' => $order->code.'-R001',
                'game' => 'nro',
                'account' => 'player-one',
                'price' => 50_000,
                'amount' => 1,
            ],
        ])
        ->and($recipient->provider_response['items'][1]['submission']['response']['http_status'])->toBe(200)
        ->and($recipient->provider_response['items'][1]['submission']['response']['body'])->toMatchArray([
            'success' => true,
            'message' => 'OK',
        ]);
    expect(json_encode($recipient->provider_response, JSON_THROW_ON_ERROR))
        ->not->toContain('sk_secret_key')
        ->not->toContain(accNroVnSignature([
            'partner_id' => 'pk_partner_123',
            'request_id' => $order->code.'-R001',
            'game' => 'nro',
            'account' => 'player-one',
            'price' => 50_000,
            'amount' => 1,
        ], 'sk_secret_key'));

    Http::assertSent(function (Request $request) use ($order): bool {
        $unsignedPayload = [
            'partner_id' => 'pk_partner_123',
            'request_id' => $order->code.'-R001',
            'game' => 'nro',
            'account' => 'player-one',
            'price' => 50_000,
            'amount' => 1,
        ];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/create'
            && $request->data() === [
                ...$unsignedPayload,
                'sign' => accNroVnSignature($unsignedPayload, 'sk_secret_key'),
            ]
            && ! array_key_exists('secret_key', $request->data());
    });
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);

    app(RecipientFulfillmentService::class)->submit($recipient->id);
    Http::assertSentCount(1);
});

test('accnrovn queries an order by request id and completes fulfillment', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    [$order, $recipient] = accNroVnOrderFixture([
        'provider_request_id' => 'LOCAL-ORDER-1002',
        'provider_reference' => 'ACC-NRO-1002',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'LOCAL-ORDER-1002',
                    'reference' => 'ACC-NRO-1002',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/query' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-NRO-1002',
                'request_id' => 'LOCAL-ORDER-1002',
                'status' => 'success',
                'status_code' => 'SUCCESS',
                'topup_id' => '1148567',
                'message' => '',
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'sync_provider'])
        ->assertSuccessful()
        ->assertJsonPath('data.provider.name', 'AccNRO')
        ->assertJsonPath('data.provider.slug', 'accnrovn')
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.can_sync_provider', false)
        ->assertJsonPath('data.recipients.0.provider_items.0.provider_topup_id', '1148567')
        ->assertJsonPath('data.recipients.0.provider_items.0.current_step', 'completed')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.request.payload.request_id', 'LOCAL-ORDER-1002')
        ->assertJsonMissingPath('data.recipients.0.provider_items.0.last_status_check.request.payload.sign')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.http_status', 200)
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.body.data.status', 'success');

    expect($recipient->refresh()->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->provider_response['items'][1]['response']['provider_topup_id'])->toBe('1148567')
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);

    Http::assertSent(function (Request $request): bool {
        $unsignedPayload = [
            'partner_id' => 'pk_partner_123',
            'request_id' => 'LOCAL-ORDER-1002',
        ];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/query'
            && $request->data() === [
                ...$unsignedPayload,
                'sign' => accNroVnSignature($unsignedPayload, 'sk_secret_key'),
            ];
    });
});

test('accnrovn keeps ambiguous provider results pending for reconciliation', function (): void {
    [, $recipient] = accNroVnOrderFixture([
        'provider_request_id' => 'LOCAL-AMBIGUOUS',
        'provider_reference' => 'ACC-NRO-AMBIGUOUS',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'LOCAL-AMBIGUOUS',
                    'reference' => 'ACC-NRO-AMBIGUOUS',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/query' => Http::response([
            'success' => true,
            'message' => 'Đang đối soát',
            'data' => [
                'order_id' => 'ACC-NRO-AMBIGUOUS',
                'status' => 'pending',
                'status_code' => 'AMBIGUOUS',
            ],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('processing')
        ->and($recipient->provider_response['items'][1]['status'])->toBe('pending')
        ->and($recipient->provider_response['items'][1]['response']['provider_code'])->toBe('AMBIGUOUS');
    Queue::assertPushed(SyncTopupRecipientStatus::class, fn (SyncTopupRecipientStatus $job): bool => $job->checkAttempt === 2);
});

test('admin refreshes accnrovn balance without sending the secret key', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $provider = TopupProvider::factory()->create([
        'name' => 'AccNRO',
        'slug' => 'accnrovn',
        'connection_config' => accNroVnConnectionConfig(),
    ]);
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/balance' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'partner_id' => 'pk_partner_123',
                'name' => 'Đại lý Nạp Carot',
                'balance' => 1_500_000,
            ],
        ]),
    ]);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/topup-providers/refresh-balances', ['provider_ids' => [$provider->id]])
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $provider->id)
        ->assertJsonPath('data.0.supports_balance', true)
        ->assertJsonPath('data.0.balance', 1_500_000)
        ->assertJsonPath('data.0.balance_currency', 'VND')
        ->assertJsonPath('data.0.balance_status', 'success');

    expect($provider->refresh()->balance)->toBe(1_500_000)
        ->and($provider->balance_status)->toBe('success')
        ->and($provider->balance_checked_at)->not->toBeNull();

    Http::assertSent(function (Request $request): bool {
        $unsignedPayload = ['partner_id' => 'pk_partner_123'];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/balance'
            && $request->data() === [
                ...$unsignedPayload,
                'sign' => accNroVnSignature($unsignedPayload, 'sk_secret_key'),
            ]
            && ! array_key_exists('secret_key', $request->data());
    });
});

/**
 * @param  array<string, mixed>  $recipientOverrides
 * @return array{0: Order, 1: OrderRecipient, 2: TopupProvider}
 */
function accNroVnOrderFixture(array $recipientOverrides = []): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'name' => 'AccNRO',
        'slug' => 'accnrovn',
        'connection_config' => accNroVnConnectionConfig(),
    ]);
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_id' => $provider->id,
        'provider_service_code' => 'nro',
        'denomination' => 50_000,
    ]);
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'topup_provider_id' => $provider->id,
        'denomination' => 50_000,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Wallet,
        'order_status' => OrderStatus::Processing,
        'paid_at' => now(),
        'processing_at' => now(),
        'metadata' => ['provider' => ['slug' => 'accnrovn', 'service_code' => 'nro']],
    ]);
    $recipient = $order->recipients()->create([
        'position' => 1,
        'recipient_data' => ['game_account' => 'player-one'],
        'quantity' => 1,
        ...$recipientOverrides,
    ]);

    return [$order, $recipient, $provider];
}

/** @return array<string, int|string> */
function accNroVnConnectionConfig(): array
{
    return [
        'base_url' => 'https://accnro.vn/api/v1/partner/recharge/create',
        'partner_key' => 'pk_partner_123',
        'serect_key' => 'sk_secret_key',
        'connect_timeout' => 5,
        'timeout' => 20,
        'max_status_checks' => 5,
    ];
}

/** @param array<string, bool|int|string> $payload */
function accNroVnSignature(array $payload, string $secretKey): string
{
    ksort($payload);

    return hash_hmac('sha256', http_build_query($payload, '', '&'), $secretKey);
}
