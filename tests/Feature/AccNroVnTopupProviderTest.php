<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
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
    'catalog endpoint' => ['https://accnro.vn/api/v1/partner/recharge/catalog'],
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

test('accnrovn creates an idempotent order with simple credentials and no signature', function (): void {
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
        ], 200, ['X-Provider-Trace' => 'acc-create-1']),
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
        ->and($recipient->provider_response['items'][1]['submission']['request']['method'])->toBe('POST')
        ->and($recipient->provider_response['items'][1]['submission']['request']['url'])
        ->toBe('https://accnro.vn/api/v1/partner/recharge/create')
        ->and($recipient->provider_response['items'][1]['submission']['request']['headers'])->toMatchArray([
            'Accept' => ['application/json'],
            'Content-Type' => ['application/json'],
        ])
        ->and($recipient->provider_response['items'][1]['submission']['request']['payload'])->toBe([
            'partner_id' => 'pk_partner_123',
            'secret_key' => 'sk_secret_key',
            'request_id' => $order->code.'-R001',
            'game' => 'nr',
            'server' => '3',
            'account' => 'user01@gmail.com',
            'price' => 50_000,
            'amount' => 1,
        ])
        ->and($recipient->provider_response['items'][1]['submission']['request']['raw_body'])
        ->toContain('"secret_key":"sk_secret_key"')
        ->and($recipient->provider_response['items'][1]['submission']['response']['headers'])
        ->toMatchArray(['X-Provider-Trace' => ['acc-create-1']])
        ->and($recipient->provider_response['items'][1]['submission']['response']['raw_body'])
        ->toContain('"order_id":"ACC-NRO-1001"')
        ->and($recipient->provider_response['items'][1]['submission']['response']['http_status'])->toBe(200)
        ->and($recipient->provider_response['items'][1]['submission']['response']['body'])->toMatchArray([
            'success' => true,
            'message' => 'OK',
        ]);
    expect(json_encode($recipient->provider_response, JSON_THROW_ON_ERROR))
        ->toContain('sk_secret_key')
        ->not->toContain('"sign"');

    Http::assertSent(function (Request $request) use ($order): bool {
        $payload = [
            'partner_id' => 'pk_partner_123',
            'secret_key' => 'sk_secret_key',
            'request_id' => $order->code.'-R001',
            'game' => 'nr',
            'server' => '3',
            'account' => 'user01@gmail.com',
            'price' => 50_000,
            'amount' => 1,
        ];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/create'
            && $request->data() === $payload
            && ! array_key_exists('sign', $request->data());
    });
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);

    app(RecipientFulfillmentService::class)->submit($recipient->id);
    Http::assertSentCount(1);
});

test('accnrovn create response completes only with success and a non empty topup id', function (mixed $topupId, string $expectedStatus): void {
    [$order, $recipient, $provider] = accNroVnOrderFixture();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-CREATE-TOPUP-ID',
                'request_id' => $order->code.'-R001',
                'status' => 'success',
                'status_code' => 'SUCCESS',
                'topup_id' => $topupId,
            ],
        ]),
    ]);

    $result = app(AccNroVnTopupProvider::class)->submit(
        $order,
        $recipient,
        $provider,
        $order->code.'-R001',
    );

    expect($result->status->value)->toBe($expectedStatus)
        ->and($result->response['provider_topup_id'])->toBe($expectedStatus === 'completed' ? trim((string) $topupId) : null);
})->with([
    'success without topup id stays pending' => [null, 'pending'],
    'success with empty topup id stays pending' => ['', 'pending'],
    'success with topup id completes' => ['TOPUP-CREATE-001', 'completed'],
]);

test('accnrovn omits server when the game server has no provider code', function (): void {
    [$order, $recipient, $provider] = accNroVnOrderFixture();
    $order->server()->update(['code' => '']);
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-NO-SERVER-1',
                'status' => 'pending',
                'status_code' => 'QUEUED',
            ],
        ]),
    ]);

    app(AccNroVnTopupProvider::class)->submit(
        $order->fresh('server'),
        $recipient,
        $provider,
        $order->code.'-R001',
    );

    Http::assertSent(fn (Request $request): bool => ($request->data()['game'] ?? null) === 'nr'
        && ! array_key_exists('server', $request->data()));
});

test('accnrovn maps each game checkout setting to account and extra fields', function (): void {
    [$order, $recipient, $provider] = accNroVnOrderFixture();
    $order->server()->update(['code' => '']);
    $order->forceFill([
        'checkout_fields_snapshot' => [
            ['key' => 'account', 'label' => 'Tên nhân vật', 'placeholder' => '', 'required' => true],
            ['key' => 'zone', 'label' => 'Khu', 'placeholder' => '', 'required' => true],
            ['key' => 'note', 'label' => 'Ghi chú', 'placeholder' => '', 'required' => false],
        ],
        'metadata' => ['provider' => ['slug' => 'accnrovn', 'service_code' => 'custom_game']],
    ])->save();
    $recipient->forceFill([
        'recipient_data' => [
            'account' => 'Songoku',
            'zone' => '7',
            'note' => '',
        ],
    ])->save();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-CUSTOM-1',
                'status' => 'pending',
                'status_code' => 'QUEUED',
            ],
        ]),
    ]);

    app(AccNroVnTopupProvider::class)->submit(
        $order->fresh('server'),
        $recipient->fresh(),
        $provider,
        $order->code.'-R001',
    );

    Http::assertSent(function (Request $request): bool {
        return ($request->data()['game'] ?? null) === 'custom_game'
            && ($request->data()['account'] ?? null) === 'Songoku'
            && ($request->data()['extra'] ?? null) === ['zone' => '7']
            && ! array_key_exists('server', $request->data());
    });
});

test('accnrovn maps an arbitrary canonical game field to provider account', function (): void {
    [$order, $recipient, $provider] = accNroVnOrderFixture();
    $order->forceFill(['checkout_fields_snapshot' => [
        ['key' => 'taikhoan', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true],
    ]])->save();
    $recipient->forceFill(['recipient_data' => ['taikhoan' => 'player-one']])->save();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => ['order_id' => 'ACC-MAPPED-1', 'status' => 'pending'],
        ]),
    ]);

    app(AccNroVnTopupProvider::class)->submit(
        $order,
        $recipient,
        $provider,
        $order->code.'-R001',
    );

    Http::assertSent(fn (Request $request): bool => ($request->data()['account'] ?? null) === 'player-one'
        && ! array_key_exists('taikhoan', $request->data()['extra'] ?? []));
});

test('accnrovn reloads and sends the server code when the order relation omitted that column', function (): void {
    [$order, $recipient, $provider] = accNroVnOrderFixture();
    $order->load('server:id,name');
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'order_id' => 'ACC-NRO-SERVER-1',
                'request_id' => $order->code.'-R001',
                'status' => 'pending',
                'status_code' => 'QUEUED',
                'server' => '3',
            ],
        ]),
    ]);

    expect($order->server?->code)->toBeNull();

    app(AccNroVnTopupProvider::class)->submit(
        $order,
        $recipient,
        $provider,
        $order->code.'-R001',
    );

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://accnro.vn/api/v1/partner/recharge/create'
        && ($request->data()['game'] ?? null) === 'nr'
        && ($request->data()['server'] ?? null) === '3');
});

test('admin can inspect an unmasked accnrovn error exchange with headers and raw bodies', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'user']);
    [$order, $recipient] = accNroVnOrderFixture();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response([
            'success' => false,
            'message' => 'Invalid credentials',
            'debug' => [
                'authorization' => 'Bearer provider-debug-token',
                'received_secret_key' => 'sk_secret_key',
            ],
        ], 401, [
            'X-Provider-Trace' => 'acc-error-401',
            'Set-Cookie' => 'provider_session=debug',
        ]),
    ]);

    app(RecipientFulfillmentService::class)->submit($recipient->id);

    $this->actingAs($user)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath(
            'data.recipients.0.provider_items.0.submission.request.payload.secret_key',
            'sk_secret_key',
        )
        ->assertJsonMissingPath('data.recipients.0.provider_items.0.submission.request.payload.sign')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.request_id', $order->code.'-R001')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.game', 'nr')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.server', '3')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.account', 'user01@gmail.com')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.headers.Content-Type.0', 'application/json')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.response.http_status', 401)
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.response.headers.X-Provider-Trace.0', 'acc-error-401')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.response.headers.Set-Cookie.0', 'provider_session=debug')
        ->assertJsonPath(
            'data.recipients.0.provider_items.0.submission.response.body.debug.authorization',
            'Bearer provider-debug-token',
        )
        ->assertJsonPath(
            'data.recipients.0.provider_items.0.submission.response.body.debug.received_secret_key',
            'sk_secret_key',
        );
});

test('admin does not invent a server field for a historical request that never sent it', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    [$order] = accNroVnOrderFixture([
        'status' => 'failed',
        'provider_response' => [
            'schema_version' => 2,
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'LEGACY-NO-SERVER',
                    'status' => 'failed',
                    'submission' => [
                        'request' => [
                            'method' => 'POST',
                            'url' => 'https://accnro.vn/api/v1/partner/recharge/create',
                            'headers' => ['Content-Type' => ['application/json']],
                            'payload' => [
                                'partner_id' => 'pk_legacy',
                                'request_id' => 'LEGACY-NO-SERVER',
                                'game' => 'nr',
                                'account' => 'legacy@example.com',
                                'price' => 50_000,
                                'amount' => 1,
                            ],
                            'raw_body' => '{"partner_id":"pk_legacy","request_id":"LEGACY-NO-SERVER","game":"nr"}',
                        ],
                        'response' => ['http_status' => 400],
                        'status' => 'failed',
                    ],
                ],
            ],
        ],
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertJsonPath('data.server', $order->server()->value('name'))
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.request_id', 'LEGACY-NO-SERVER')
        ->assertJsonPath('data.recipients.0.provider_items.0.submission.request.payload.game', 'nr')
        ->assertJsonMissingPath('data.recipients.0.provider_items.0.submission.request.payload.server');
});

test('accnrovn stores a full retryable provider error before rethrowing it', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    [$order, $recipient] = accNroVnOrderFixture();
    Http::fake([
        'https://accnro.vn/api/v1/partner/recharge/create' => Http::response(
            '<html>provider maintenance</html>',
            503,
            [
                'Retry-After' => '30',
                'X-Provider-Trace' => 'acc-error-503',
            ],
        ),
    ]);

    expect(fn () => app(RecipientFulfillmentService::class)->submit($recipient->id))
        ->toThrow(TopupProviderConnectionException::class);

    $item = $recipient->refresh()->provider_response['items'][1];

    expect($recipient->status)->toBe('processing')
        ->and($item['last_error']['request']['url'])->toBe('https://accnro.vn/api/v1/partner/recharge/create')
        ->and($item['last_error']['request']['headers']['Content-Type'])->toBe(['application/json'])
        ->and($item['last_error']['request']['payload']['secret_key'])
        ->toBe('sk_secret_key')
        ->and($item['last_error']['request']['raw_body'])
        ->toContain('"secret_key":"sk_secret_key"')
        ->and($item['last_error']['response']['http_status'])->toBe(503)
        ->and($item['last_error']['response']['headers']['Retry-After'])->toBe(['30'])
        ->and($item['last_error']['response']['headers']['X-Provider-Trace'])->toBe(['acc-error-503'])
        ->and($item['last_error']['response']['body'])->toBe('<html>provider maintenance</html>')
        ->and($item['last_error']['response']['raw_body'])->toBe('<html>provider maintenance</html>')
        ->and($item['last_error']['message'])->toContain('[provider_unavailable]');

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_error.request.payload.secret_key', 'sk_secret_key')
        ->assertJsonMissingPath('data.recipients.0.provider_items.0.last_error.request.payload.sign')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_error.response.http_status', 503)
        ->assertJsonPath('data.recipients.0.provider_items.0.last_error.response.headers.Retry-After.0', '30')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_error.response.raw_body', '<html>provider maintenance</html>');
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
        ], 200, ['X-Provider-Trace' => 'acc-query-1']),
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'sync_provider'])
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.provider.name', 'AccNRO')
        ->assertJsonPath('data.provider.slug', 'accnrovn')
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.can_sync_provider', true)
        ->assertJsonPath('data.recipients.0.provider_items.0.provider_topup_id', '1148567')
        ->assertJsonPath('data.recipients.0.provider_items.0.current_step', 'completed')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.request.payload.request_id', 'LOCAL-ORDER-1002')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.request.payload.secret_key', 'sk_secret_key')
        ->assertJsonMissingPath('data.recipients.0.provider_items.0.last_status_check.request.payload.sign')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.request.headers.Content-Type.0', 'application/json')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.http_status', 200)
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.headers.X-Provider-Trace.0', 'acc-query-1')
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.body.data.status', 'success');

    expect($recipient->refresh()->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->provider_response['items'][1]['response']['provider_topup_id'])->toBe('1148567')
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);

    Http::assertSent(function (Request $request): bool {
        $payload = [
            'partner_id' => 'pk_partner_123',
            'secret_key' => 'sk_secret_key',
            'request_id' => 'LOCAL-ORDER-1002',
        ];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/query'
            && $request->data() === $payload
            && ! array_key_exists('sign', $request->data());
    });
});

test('accnrovn keeps success pending until provider returns a non empty topup id', function (mixed $topupId): void {
    [, $recipient] = accNroVnOrderFixture([
        'provider_request_id' => 'LOCAL-WAIT-TOPUP-ID',
        'provider_reference' => 'ACC-NRO-WAIT-TOPUP-ID',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'LOCAL-WAIT-TOPUP-ID',
                    'reference' => 'ACC-NRO-WAIT-TOPUP-ID',
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
                'order_id' => 'ACC-NRO-WAIT-TOPUP-ID',
                'request_id' => 'LOCAL-WAIT-TOPUP-ID',
                'status' => 'success',
                'status_code' => 'SUCCESS',
                'topup_id' => $topupId,
            ],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('processing')
        ->and($recipient->provider_status)->toBe('processing')
        ->and($recipient->completed_at)->toBeNull()
        ->and($recipient->provider_response['items'][1]['status'])->toBe('pending')
        ->and($recipient->provider_response['items'][1]['response']['provider_status'])->toBe('success')
        ->and($recipient->provider_response['items'][1]['response']['provider_topup_id'])->toBeNull();
    Queue::assertPushed(SyncTopupRecipientStatus::class, fn (SyncTopupRecipientStatus $job): bool => $job->checkAttempt === 2);
})->with([
    'missing topup id' => [null],
    'empty topup id' => [''],
    'blank topup id' => ['   '],
]);

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

test('admin refreshes accnrovn balance with simple credentials and no signature', function (): void {
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
        $payload = [
            'partner_id' => 'pk_partner_123',
            'secret_key' => 'sk_secret_key',
        ];

        return $request->url() === 'https://accnro.vn/api/v1/partner/recharge/balance'
            && $request->data() === $payload
            && ! array_key_exists('sign', $request->data());
    });
});

/**
 * @param  array<string, mixed>  $recipientOverrides
 * @return array{0: Order, 1: OrderRecipient, 2: TopupProvider}
 */
function accNroVnOrderFixture(array $recipientOverrides = []): array
{
    $game = Game::factory()->create([
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Email/Số điện thoại', 'placeholder' => '', 'required' => true],
        ],
    ]);
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'name' => 'AccNRO',
        'slug' => 'accnrovn',
        'connection_config' => accNroVnConnectionConfig(),
    ]);
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_id' => $provider->id,
        'provider_service_code' => 'nr',
        'denomination' => 50_000,
    ]);
    $order = Order::factory()->create([
        'code' => 'OD-ACC-1001',
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
        'metadata' => ['provider' => ['slug' => 'accnrovn', 'service_code' => 'nr']],
    ]);
    $recipient = $order->recipients()->create([
        'position' => 1,
        'recipient_data' => ['account' => 'user01@gmail.com'],
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
