<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Features\Topup\Jobs\ProcessTopupRecipient;
use App\Features\Topup\Jobs\SyncTopupRecipientStatus;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Features\Topup\Services\TopupService;
use App\Mail\Orders\OrderCompletedMail;
use App\Mail\Orders\OrderFailedMail;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
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

test('the9p submission uses a stable request id and queues status synchronization', function (): void {
    [$order, $recipient, $provider] = the9pOrderFixture();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::sequence()
            ->push([
                'status' => 'success',
                'message' => 'accepted',
                'data' => ['order_code' => 'THE9P-1001', 'status' => 'pending'],
            ])
            ->push([
                'status' => 'success',
                'message' => 'accepted',
                'data' => ['order_code' => 'THE9P-1002', 'status' => 'pending'],
            ]),
    ]);

    app(RecipientFulfillmentService::class)->submit($recipient->id);

    $recipient->refresh();
    expect($recipient->provider_request_id)->toBe($order->code.'-R001')
        ->and($recipient->provider_reference)->toBe('THE9P-1001')
        ->and($recipient->provider_status)->toBe('processing')
        ->and($recipient->status)->toBe('processing')
        ->and($recipient->submitted_at)->not->toBeNull()
        ->and($recipient->provider_response['items'][1]['request_id'])->toBe($order->code.'-R001-U001')
        ->and($order->refresh()->provider_reference)->toBeNull();

    Http::assertSent(function (Request $request) use ($order, $provider): bool {
        $payload = $request->data();

        return $request->url() === 'https://the9p.com/api/rechargews'
            && $payload['command'] === 'topup'
            && $payload['partner_id'] === 'partner-123'
            && $payload['request_id'] === $order->code.'-R001-U001'
            && $payload['service_code'] === 'nr'
            && $payload['amount'] === 10000
            && $payload['account_info'] === ['server' => 3, 'username' => 'player-one']
            && $payload['sign'] === md5('secret-key'.$provider->connection_config['partner_id'].'topup'.$order->code.'-R001-U001');
    });
    Queue::assertPushed(SyncTopupRecipientStatus::class, 1);

    app(RecipientFulfillmentService::class)->submit($recipient->id);
    Http::assertSentCount(1);

    app(RecipientFulfillmentService::class)->submit($recipient->id, 2);
    $recipient->refresh();

    expect($recipient->provider_response['items'][2]['request_id'])->toBe($order->code.'-R001-U002')
        ->and($recipient->provider_response['items'][2]['reference'])->toBe('THE9P-1002');
    Http::assertSentCount(2);
    Queue::assertPushed(SyncTopupRecipientStatus::class, 2);
});

test('multiple recipients submit once each with the same selected denomination', function (): void {
    [$order, $firstRecipient] = the9pOrderFixture(['quantity' => 1]);
    $secondRecipient = $order->recipients()->create([
        'position' => 2,
        'recipient_data' => ['game_account' => 'player-two'],
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
            'data' => ['order_code' => 'THE9P-2002', 'status' => 'completed'],
        ]),
    ]);
    Event::fake([OrderStatusUpdated::class]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('completed')
        ->and($recipient->provider_status)->toBe('completed')
        ->and($recipient->completed_at)->not->toBeNull()
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Completed)
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
            'data' => ['order_code' => 'THE9P-FAILED', 'status' => 'failed'],
        ]),
    ]);

    app(RecipientFulfillmentService::class)->syncStatus($recipient->id, 1, 1);

    expect($recipient->refresh()->status)->toBe('failed')
        ->and($recipient->provider_status)->toBe('failed')
        ->and($recipient->failed_at)->not->toBeNull()
        ->and($order->refresh()->order_status)->toBe(OrderStatus::Failed)
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
        ->and($recipient->provider_response['items'][1]['status'])->toBe('pending');
    Queue::assertPushed(SyncTopupRecipientStatus::class);
});

test('paid multi recipient order is split into queue jobs without provider http in the request flow', function (): void {
    [$order] = the9pOrderFixture();
    $order->recipients()->create([
        'position' => 2,
        'recipient_data' => ['game_account' => 'player-two'],
        'quantity' => 3,
    ]);

    app(TopupService::class)->process($order->id);

    expect($order->refresh()->order_status)->toBe(OrderStatus::Processing);
    Queue::assertPushed(ProcessTopupRecipient::class, 5);
    Queue::assertPushed(ProcessTopupRecipient::class, fn (ProcessTopupRecipient $job): bool => $job->recipientId === $order->recipients()->first()->id
        && $job->unit === 2);
    Http::assertNothingSent();
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
    $game = Game::factory()->create();
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
        'provider_service_code' => 'nr',
    ]);

    $this->post(route('checkout.store'), [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'player-one', 'game_character' => ''],
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
    $game = Game::factory()->create();
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
        'provider_service_code' => 'nr',
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
        'recipient_data' => ['game_account' => 'player-one'],
        'quantity' => 2,
        ...$recipientOverrides,
    ]);

    return [$order, $recipient, $provider];
}
