<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Reporting\Jobs\SendDiscordReport;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Features\Topup\Jobs\ProcessTopupRecipient;
use App\Features\Topup\Jobs\SyncTopupRecipientStatus;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Features\Topup\Services\TopupService;
use App\Mail\Orders\OrderCompletedMail;
use App\Mail\Orders\OrderFailedMail;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Mail::fake();
    Queue::fake();
});

test('the9p submits all units in one request using account quantity', function (): void {
    [$order, $recipient, $provider] = the9pOrderFixture(['quantity' => 8]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'accepted',
            'data' => ['order_code' => 'THE9P-1001', 'status' => 'pending'],
        ], 200, ['X-Provider-Trace' => 'the9p-create-1']),
    ]);

    app(RecipientFulfillmentService::class)->submit($recipient->id);

    $recipient->refresh();
    expect($recipient->provider_request_id)->toBe($order->code.'-R001')
        ->and($recipient->provider_reference)->toBe('THE9P-1001')
        ->and($recipient->provider_status)->toBe('processing')
        ->and($recipient->status)->toBe('processing')
        ->and($recipient->submitted_at)->not->toBeNull()
        ->and($recipient->provider_response['items'][1]['request_id'])->toBe($order->code.'-R001')
        ->and($recipient->provider_response['items'][1]['quantity'])->toBe(8)
        ->and($recipient->provider_response['items'][1]['submission']['request']['payload'])->toBe([
            'command' => 'topup',
            'partner_id' => 'partner-123',
            'request_id' => $order->code.'-R001',
            'service_code' => 'nr',
            'amount' => 10000,
            'account_info' => ['server' => 3, 'username' => 'player-one', 'qty' => 8],
            'sign' => md5('secret-key'.$provider->connection_config['partner_id'].'topup'.$order->code.'-R001'),
        ])
        ->and($recipient->provider_response['items'][1]['submission']['request']['headers'])->toMatchArray([
            'Accept' => ['application/json'],
            'Content-Type' => ['application/json'],
        ])
        ->and($recipient->provider_response['items'][1]['submission']['request']['raw_body'])
        ->toContain('"sign":"'.md5('secret-key'.$provider->connection_config['partner_id'].'topup'.$order->code.'-R001').'"')
        ->and($recipient->provider_response['items'][1]['submission']['response']['http_status'])->toBe(200)
        ->and($recipient->provider_response['items'][1]['submission']['response']['headers'])
        ->toMatchArray(['X-Provider-Trace' => ['the9p-create-1']])
        ->and($recipient->provider_response['items'][1]['submission']['response']['raw_body'])
        ->toContain('"order_code":"THE9P-1001"')
        ->and($recipient->provider_response['items'][1]['submission']['response']['body'])->toMatchArray([
            'status' => 'success',
            'message' => 'accepted',
        ])
        ->and($order->refresh()->provider_reference)->toBe('THE9P-1001');
    expect(json_encode($recipient->provider_response, JSON_THROW_ON_ERROR))
        ->not->toContain('secret-key')
        ->toContain(md5('secret-key'.$provider->connection_config['partner_id'].'topup'.$order->code.'-R001'));

    Http::assertSent(function (Request $request) use ($order, $provider): bool {
        $payload = $request->data();

        return $request->url() === 'https://the9p.com/api/rechargews'
            && $payload['command'] === 'topup'
            && $payload['partner_id'] === 'partner-123'
            && $payload['request_id'] === $order->code.'-R001'
            && $payload['service_code'] === 'nr'
            && $payload['amount'] === 10000
            && $payload['account_info'] === ['server' => 3, 'username' => 'player-one', 'qty' => 8]
            && $payload['sign'] === md5('secret-key'.$provider->connection_config['partner_id'].'topup'.$order->code.'-R001');
    });
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);

    app(RecipientFulfillmentService::class)->submit($recipient->id);
    Http::assertSentCount(1);

    app(RecipientFulfillmentService::class)->submit($recipient->id, 2);
    Http::assertSentCount(1);
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);
});

test('merchant provider maps canonical fields with the order mapping snapshot', function (): void {
    [$order, $recipient, $provider] = the9pOrderFixture([
        'recipient_data' => [
            'username' => 'hso-player',
            'character' => 'hso-hero',
        ],
    ]);
    $provider->update([
        'payload_field_mapping' => [
            'default' => [],
            'services' => ['hso' => ['username' => 'wrong_live_key']],
        ],
    ]);
    $order->forceFill([
        'checkout_fields_snapshot' => [
            ['key' => 'username', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true],
            ['key' => 'character', 'label' => 'Nhân vật', 'placeholder' => '', 'required' => true],
        ],
        'metadata' => ['provider' => [
            'slug' => 'the9p',
            'service_code' => 'hso',
            'payload_field_mapping' => [
                'default' => ['character' => 'character_name'],
                'services' => ['hso' => [
                    'username' => 'user_account',
                    'character' => 'charactor',
                ]],
            ],
        ]],
    ])->save();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['order_code' => 'THE9P-HSO-1', 'status' => 'pending'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->submit($recipient->id);

    Http::assertSent(function (Request $request): bool {
        return ($request->data()['service_code'] ?? null) === 'hso'
            && ($request->data()['account_info'] ?? null) === [
                'server' => 3,
                'user_account' => 'hso-player',
                'charactor' => 'hso-hero',
                'qty' => 2,
            ];
    });

    expect(data_get($recipient->refresh()->provider_response, 'items.1.submission.request.payload.account_info'))
        ->toBe([
            'server' => 3,
            'user_account' => 'hso-player',
            'charactor' => 'hso-hero',
            'qty' => 2,
        ]);
});

test('the9p accepts a legacy numeric success status for a batch order', function (): void {
    [$order, $recipient] = the9pOrderFixture(['quantity' => 8]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::sequence()
            ->push([
                'status' => 'success',
                'data' => ['order_code' => 'THE9P-BATCH-8', 'status' => 'pending'],
            ])
            ->push([
                'status' => 1,
                'message' => 'Thành công',
                'data' => ['order_code' => 'THE9P-BATCH-8', 'topup_id' => 'THE9P-TOPUP-BATCH-8'],
            ]),
    ]);

    $fulfillmentService = app(RecipientFulfillmentService::class);
    $fulfillmentService->submit($recipient->id);
    $fulfillmentService->syncStatus($recipient->id, 1, 1);

    $recipient->refresh();
    expect($recipient->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->provider_response['items'][1]['quantity'])->toBe(8)
        ->and($recipient->provider_response['items'][1]['status'])->toBe('completed')
        ->and($recipient->provider_response['items'][1]['response']['provider_status'])->toBe('1')
        ->and($recipient->provider_response['items'][1]['response']['envelope_status'])->toBe('1')
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed)
        ->and($order->topup_id)->toBe('THE9P-TOPUP-BATCH-8');
    Http::assertSentCount(2);
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);
    Mail::assertQueued(OrderCompletedMail::class, 1);
});

test('multiple recipients submit once each with the same selected denomination', function (): void {
    [$order, $firstRecipient] = the9pOrderFixture(['quantity' => 1]);
    $secondRecipient = $order->recipients()->create([
        'position' => 2,
        'recipient_data' => ['username' => 'player-two'],
        'quantity' => 1,
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::sequence()
            ->push(['status' => 'success', 'data' => ['order_code' => 'THE9P-MULTI-1', 'status' => 'pending']])
            ->push(['status' => 'success', 'data' => ['order_code' => 'THE9P-MULTI-2', 'status' => 'pending']]),
    ]);

    $fulfillmentService = app(RecipientFulfillmentService::class);
    $fulfillmentService->submit($firstRecipient->id);
    $fulfillmentService->submit($secondRecipient->id);

    $topupRequests = Http::recorded()
        ->map(fn (array $record): array => $record[0]->data())
        ->values();

    expect($topupRequests)->toHaveCount(2)
        ->and($topupRequests->pluck('amount')->all())->toBe([10000, 10000])
        ->and($topupRequests->pluck('account_info.qty')->all())->toBe([1, 1])
        ->and($topupRequests->pluck('account_info.username')->all())->toBe(['player-one', 'player-two'])
        ->and($topupRequests->pluck('request_id')->all())->toBe([
            $order->code.'-R001',
            $order->code.'-R002',
        ]);
});

test('provider status completion updates recipient and parent order then queues email', function (): void {
    [$order, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-STATUS-R001',
        'provider_reference' => 'THE9P-2002',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-STATUS-R001',
                    'reference' => 'THE9P-2002',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['order_code' => 'THE9P-2002', 'status' => 'completed', 'topup_id' => 'THE9P-TOPUP-2002'],
        ]),
    ]);
    Event::fake([OrderStatusUpdated::class]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->completed_at)->not->toBeNull()
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed)
        ->and($order->topup_id)->toBe('THE9P-TOPUP-2002')
        ->and($order->completed_at)->not->toBeNull();
    Mail::assertQueued(OrderCompletedMail::class, 1);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);
    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->orderStatus === OrderStatus::Completed->value
        && $event->recipientSummary['completed'] === 1);
    Http::assertSent(fn (Request $request): bool => $request['command'] === 'getstatus'
        && $request['partner_id'] === 'partner-123'
        && $request['request_id'] === 'TOP-STATUS-R001'
        && $request['order_code'] === 'THE9P-2002'
        && $request['sign'] === md5('secret-keypartner-123getstatusTOP-STATUS-R001'));
});

test('pending provider status is checked again by the topup queue', function (): void {
    [, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-PENDING-R001',
        'provider_reference' => 'THE9P-PENDING',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-PENDING-R001',
                    'reference' => 'THE9P-PENDING',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['order_code' => 'THE9P-PENDING', 'status' => 'processing'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    $recipient->refresh();
    expect($recipient->status)->toBe('processing')
        ->and($recipient->provider_response['items'][1]['status'])->toBe('processing')
        ->and($recipient->provider_response['items'][1]['check_attempts'])->toBe(1)
        ->and($recipient->last_checked_at)->not->toBeNull();
    Queue::assertPushed(SyncTopupRecipientStatus::class, fn (SyncTopupRecipientStatus $job): bool => $job->recipientId === $recipient->id
        && $job->unit === 1
        && $job->checkAttempt === 2
        && $job->delay !== null);
});

test('failed provider status fails the recipient and parent order', function (): void {
    [$order, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-FAILED-R001',
        'provider_reference' => 'THE9P-FAILED',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-FAILED-R001',
                    'reference' => 'THE9P-FAILED',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['order_code' => 'THE9P-FAILED', 'status' => 'failed', 'message' => 'Provider hết số dư'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('failed')
        ->and($recipient->provider_status)->toBe('failed')
        ->and($recipient->failure_reason)->toBe('Provider hết số dư')
        ->and($recipient->failed_at)->not->toBeNull()
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Failed)
        ->and($order->failure_reason)->toBe('Provider hết số dư')
        ->and($order->failed_at)->not->toBeNull();
    Mail::assertQueued(OrderFailedMail::class, 1);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);
});

test('status checks stop at the configured limit and require manual review', function (): void {
    [, $recipient, $provider] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-LIMIT-R001',
        'provider_reference' => 'THE9P-LIMIT',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-LIMIT-R001',
                    'reference' => 'THE9P-LIMIT',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    $provider->update([
        'connection_config' => [
            ...$provider->connection_config,
            'max_status_checks' => 1,
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['order_code' => 'THE9P-LIMIT', 'status' => 'pending'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    $recipient->refresh();
    expect($recipient->status)->toBe('processing')
        ->and($recipient->status_check_attempts)->toBe(1)
        ->and($recipient->failure_reason)->toContain('đối soát thủ công');
    Http::assertSentCount(1);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);
});

test('a successful response envelope without a transaction status is not treated as completed', function (): void {
    [, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-MALFORMED-R001',
        'provider_reference' => 'THE9P-MALFORMED',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-MALFORMED-R001',
                    'reference' => 'THE9P-MALFORMED',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['order_code' => 'THE9P-MALFORMED'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('processing')
        ->and($recipient->provider_response['items'][1]['status'])->toBe('pending')
        ->and($recipient->provider_response['items'][1]['response']['provider_status'])->toBeNull()
        ->and($recipient->provider_response['items'][1]['response']['envelope_status'])->toBe('success');
    Queue::assertPushed(SyncTopupRecipientStatus::class);
});

test('admin can immediately reconcile a processing order with the provider', function (): void {
    [$order, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-SYNC-R001',
        'provider_reference' => 'THE9P-SYNC',
        'provider_status' => 'pending',
        'status' => 'processing',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-SYNC-R001',
                    'reference' => 'THE9P-SYNC',
                    'status' => 'pending',
                ],
            ],
        ],
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['order_code' => 'THE9P-SYNC', 'status' => 'completed'],
        ]),
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertJsonPath('data.can_sync_provider', true);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'sync_provider'])
        ->assertSuccessful()
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.can_sync_provider', true);

    expect($recipient->refresh()->status)->toBe('completed')
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed);
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);
});

test('admin can refresh provider data for a completed order without reopening it', function (): void {
    [$order, $recipient] = the9pOrderFixture([
        'quantity' => 1,
        'provider_request_id' => 'TOP-COMPLETED-R001',
        'provider_reference' => 'THE9P-COMPLETED',
        'provider_status' => 'completed',
        'status' => 'completed',
        'completed_at' => now()->subHour(),
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => 'TOP-COMPLETED-R001',
                    'reference' => 'THE9P-COMPLETED',
                    'status' => 'completed',
                    'check_attempts' => 2,
                ],
            ],
        ],
    ]);
    $completedAt = now()->subMinutes(30)->startOfSecond();
    $recipientCompletedAt = $recipient->completed_at;
    $order->forceFill([
        'order_status' => OrderStatus::Completed,
        'completed_at' => $completedAt,
    ])->save();
    Queue::fake();
    Mail::fake();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Provider data refreshed',
            'data' => [
                'order_code' => 'THE9P-COMPLETED',
                'status' => 'processing',
                'topup_id' => 'TOPUP-LATE-001',
            ],
        ]),
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson("/api/admin-api/orders/{$order->code}")
        ->assertSuccessful()
        ->assertJsonPath('data.can_sync_provider', true);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'sync_provider'])
        ->assertSuccessful()
        ->assertJsonPath('data.order_status', 'completed')
        ->assertJsonPath('data.can_sync_provider', true)
        ->assertJsonPath('data.recipients.0.provider_items.0.status', 'completed')
        ->assertJsonPath('data.recipients.0.provider_items.0.check_attempts', 3)
        ->assertJsonPath('data.recipients.0.provider_items.0.last_status_check.response.body.data.topup_id', 'TOPUP-LATE-001');

    $order->refresh();
    $recipient->refresh();

    expect($order->order_status)->toBe(OrderStatus::Completed)
        ->and($order->completed_at->equalTo($completedAt))->toBeTrue()
        ->and($recipient->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->completed_at->equalTo($recipientCompletedAt))->toBeTrue()
        ->and(data_get($recipient->provider_response, 'items.1.status'))->toBe('completed')
        ->and(data_get($recipient->provider_response, 'items.1.last_status_check.status'))->toBe('processing')
        ->and(AdminAuditLog::query()->where([
            'admin_id' => $admin->id,
            'action' => 'order_sync_provider',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
        ])->exists())->toBeTrue();
    Queue::assertNotPushed(SyncTopupRecipientStatus::class);
    Mail::assertNothingQueued();
    Http::assertSentCount(1);
});

test('paid multi recipient order queues one batch job per recipient without provider create http in the request flow', function (): void {
    [$order] = the9pOrderFixture(['quantity' => 8]);
    $order->package()->update(['provider_price' => 10000]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['balance' => 1000000, 'currency' => 'VND'],
        ]),
    ]);
    $order->recipients()->create([
        'position' => 2,
        'recipient_data' => ['username' => 'player-two'],
        'quantity' => 3,
    ]);
    Queue::fake();

    app(TopupService::class)->process($order->id);

    expect($order->refresh()->order_status)->toBe(OrderStatus::Processing);
    Queue::assertPushed(ProcessTopupRecipient::class, 2);
    Queue::assertPushed(ProcessTopupRecipient::class, fn (ProcessTopupRecipient $job): bool => $job->recipientId === $order->recipients()->first()->id
        && $job->unit === 1);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['command'] === 'getbalance');
});

test('insufficient provider balance diverts the whole order to manual handling without submitting cards', function (): void {
    config()->set('services.discord.channels.provider', 'https://discord.test/provider');
    [$order, $recipient, $provider] = the9pOrderFixture();
    $order->package()->update(['provider_price' => 10000]);
    $provider->update([
        'balance' => 15000,
        'balance_currency' => 'vnd',
        'balance_status' => 'success',
        'balance_checked_at' => now(),
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['balance' => 15000, 'currency' => 'VND'],
        ]),
    ]);
    Queue::fake();

    app(TopupService::class)->process($order->id);

    $recipient->refresh();
    expect($order->refresh()->order_status)->toBe(OrderStatus::Processing)
        ->and($order->failure_reason)->toContain('Provider không đủ số dư')
        ->and($recipient->status)->toBe('processing')
        ->and($recipient->provider_status)->toBe('processing')
        ->and($recipient->failure_reason)->toContain('chưa gửi sang provider')
        ->and(data_get($recipient->provider_response, 'manual_review'))->toMatchArray([
            'code' => 'provider_balance_insufficient',
            'provider_balance' => 15000,
            'required_balance' => 20000,
            'currency' => 'VND',
        ])
        ->and(data_get($recipient->provider_response, 'items.1.message'))
        ->toBe('Chờ admin xử lý thủ công; chưa gửi yêu cầu sang provider.')
        ->and(data_get($recipient->provider_response, 'items.1.quantity'))->toBe(2)
        ->and(data_get($recipient->provider_response, 'items.1.failure_reason'))
        ->toContain('Provider không đủ số dư');
    Queue::assertNotPushed(ProcessTopupRecipient::class);
    Queue::assertPushed(SendDiscordReport::class, 1);
    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($order, $provider): bool {
        return $job->channel === 'provider'
            && $job->dedupeKey === "topup-order:{$order->id}:provider-balance-insufficient"
            && $job->details['Mã đơn'] === $order->code
            && $job->details['Nhà cung cấp'] === $provider->name
            && $job->details['Số dư hiện tại'] === '15.000 VND'
            && $job->details['Chi phí cần thiết'] === '20.000 VND';
    });
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['command'] === 'getbalance');

    app(TopupService::class)->process($order->id);
    app(RecipientFulfillmentService::class)->submit($recipient->id, 1);

    Queue::assertNotPushed(ProcessTopupRecipient::class);
    Queue::assertPushed(SendDiscordReport::class, 1);
    Http::assertSentCount(1);
});

test('sufficient provider balance keeps automatic recipient dispatch enabled', function (): void {
    [$order, , $provider] = the9pOrderFixture();
    $order->package()->update(['provider_price' => 10000]);
    $provider->update([
        'balance' => 20000,
        'balance_currency' => 'vnd',
        'balance_status' => 'success',
        'balance_checked_at' => now(),
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['balance' => 20000, 'currency' => 'VND'],
        ]),
    ]);
    Queue::fake();

    app(TopupService::class)->process($order->id);

    Queue::assertPushed(ProcessTopupRecipient::class, 1);
    Queue::assertNotPushed(SendDiscordReport::class);
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request['command'] === 'getbalance');
});

test('checkout rejects an incomplete automatic provider before creating an order', function (): void {
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => ['base_url' => 'https://the9p.com/api/rechargews', 'partner_id' => '', 'partner_key' => ''],
    ]);
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_id' => $provider->id,
        'provider_service_code' => null,
    ]);

    $this->from(route('home'))->post(route('checkout.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'player-one', 'game_character' => ''],
        'email' => 'guest@example.com',
        'payment_method' => PaymentMethod::BankTransfer->value,
    ])->assertRedirect(route('home'))->assertSessionHasErrors([
        'package_id' => 'Gói nạp hiện chưa sẵn sàng. Vui lòng chọn gói khác hoặc liên hệ hỗ trợ.',
    ]);

    expect(Order::query()->count())->toBe(0);
});

test('checkout accepts a configured provider and stores only an internal routing snapshot', function (): void {
    $game = Game::factory()->create([
        'provider_service_code' => 'nr',
        'checkout_fields' => [
            ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true],
        ],
    ]);
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_id' => $provider->id,
    ]);

    $this->post(route('checkout.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['account' => 'player-one'],
        'email' => 'guest@example.com',
        'payment_method' => PaymentMethod::BankTransfer->value,
    ])->assertRedirect();

    $order = Order::query()->sole();
    expect($order->topup_provider_id)->toBe($provider->id)
        ->and(data_get($order->metadata, 'provider.service_code'))->toBe('nr')
        ->and($order->toArray())->not->toHaveKeys(['topup_provider_id', 'provider_reference', 'metadata']);
    Http::assertNothingSent();
});

test('provider partner key is masked by the admin resource and encrypted at rest', function (): void {
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'top-secret-partner-key',
        ],
    ]);

    expect($provider->maskedConnectionConfig()['partner_key'])->toBe(TopupProvider::SECRET_MASK)
        ->and($provider->getRawOriginal('connection_config'))->not->toContain('top-secret-partner-key');
});

test('client model serialization hides every provider implementation detail', function (): void {
    [$order, $recipient] = the9pOrderFixture([
        'provider_request_id' => 'PRIVATE-REQUEST-ID',
        'provider_reference' => 'PRIVATE-PROVIDER-REFERENCE',
        'provider_status' => 'pending',
        'provider_response' => ['provider_code' => 'private-code'],
    ]);
    $order->load(['provider', 'package.provider', 'recipients']);

    expect($order->toArray())
        ->not->toHaveKeys(['topup_provider_id', 'provider', 'provider_reference', 'metadata'])
        ->and($order->package->toArray())
        ->not->toHaveKeys(['provider_id', 'provider', 'provider_service_code', 'provider_price', 'metadata'])
        ->and($recipient->fresh()->toArray())
        ->not->toHaveKeys(['provider_request_id', 'provider_reference', 'provider_status', 'provider_response']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('the9p')
        ->assertDontSee('partner-123')
        ->assertDontSee('PRIVATE-PROVIDER-REFERENCE');
    $this->withSession(["orders.access.{$order->code}" => true])
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertDontSee('the9p')
        ->assertDontSee('partner-123')
        ->assertDontSee('PRIVATE-PROVIDER-REFERENCE');
});

/**
 * @param  array<string, mixed>  $recipientOverrides
 * @return array{0:Order,1:OrderRecipient,2:TopupProvider}
 */
function the9pOrderFixture(array $recipientOverrides = []): array
{
    $game = Game::factory()->create([
        'provider_service_code' => 'nr',
        'checkout_fields' => [
            ['key' => 'username', 'label' => 'Tài khoản', 'placeholder' => '', 'required' => true],
        ],
    ]);
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
            'connect_timeout' => 5,
            'timeout' => 20,
            'max_status_checks' => 5,
        ],
    ]);
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_id' => $provider->id,
        'denomination' => 10000,
    ]);
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => $package->id,
        'topup_provider_id' => $provider->id,
        'denomination' => 10000,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::Wallet,
        'order_status' => OrderStatus::Processing,
        'paid_at' => now(),
        'processing_at' => now(),
        'metadata' => ['provider' => ['slug' => 'the9p', 'service_code' => 'nr']],
    ]);
    $recipient = $order->recipients()->create([
        'position' => 1,
        'recipient_data' => ['account' => 'player-one'],
        'quantity' => 2,
        ...$recipientOverrides,
    ]);

    return [$order, $recipient, $provider];
}
