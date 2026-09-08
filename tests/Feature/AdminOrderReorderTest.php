<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Reporting\Jobs\SendDiscordReport;
use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Features\Topup\Jobs\ProcessTopupRecipient;
use App\Features\Topup\Jobs\ReportReorderedOrderSuccess;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Features\Topup\Services\TopupProviderBalanceService;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('only admins can reorder a topup order', function (): void {
    [$order] = reorderableOrderFixture();

    $this->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'reorder'])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'reorder'])
        ->assertForbidden();
});

test('admin reorders only confirmed failed units with a fresh request id and reports provider balances after completion', function (): void {
    [$order, $recipient] = reorderableOrderFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    Mail::fake();
    Queue::fake();
    config(['services.discord.channels.provider' => 'https://discord.test/provider']);
    Http::preventStrayRequests();
    Http::fakeSequence('https://the9p.com/api/rechargews')
        ->push(['status' => 'success', 'data' => ['balance' => 5_000_000, 'currency' => 'VND']])
        ->push(['status' => 'success', 'data' => ['order_code' => 'THE9P-NEW', 'status' => 'success']])
        ->push(['status' => 'success', 'data' => ['balance' => 4_900_000, 'currency' => 'VND']]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/orders')
        ->assertOk()
        ->assertJsonPath('data.data.0.can_reorder', true);

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'reorder'])
        ->assertOk()
        ->assertJsonPath('data.order_status', 'processing')
        ->assertJsonPath('data.can_reorder', false);

    $order->refresh();
    $recipient->refresh();
    $items = $recipient->provider_response['items'];

    expect($order->order_status)->toBe(OrderStatus::Processing)
        ->and($order->failed_at)->toBeNull()
        ->and(data_get($order->metadata, 'reorder.attempt'))->toBe(1)
        ->and(data_get($order->metadata, 'reorder.requested_by'))->toBe($admin->id)
        ->and(data_get($order->metadata, 'reorder.balance_before'))->toBe(5_000_000)
        ->and(data_get($order->metadata, 'reorder.currency'))->toBe('VND')
        ->and($recipient->status)->toBe('processing')
        ->and($recipient->provider_request_id)->toBe($order->code.'-R001-A001')
        ->and($items[1]['status'])->toBe('completed')
        ->and($items[1]['request_id'])->toBe($order->code.'-OLD-R001-U001')
        ->and($items[2])->toMatchArray([
            'unit' => 2,
            'request_id' => $order->code.'-R001-A001-U002',
            'status' => 'pending',
        ])
        ->and($recipient->provider_response['reorder_history'][0]['items'][0]['request_id'])->toBe($order->code.'-OLD-R001-U002');

    Queue::assertPushed(ProcessTopupRecipient::class, 1);
    Queue::assertPushed(ProcessTopupRecipient::class, fn (ProcessTopupRecipient $job): bool => $job->recipientId === $recipient->id && $job->unit === 2);
    Queue::assertNotPushed(ProcessTopupRecipient::class, fn (ProcessTopupRecipient $job): bool => $job->recipientId === $recipient->id && $job->unit === 1);
    expect(AdminAuditLog::query()->where([
        'admin_id' => $admin->id,
        'action' => 'order_reorder',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
    ])->exists())->toBeTrue();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'reorder'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reorder');
    Queue::assertPushed(ProcessTopupRecipient::class, 1);

    app(RecipientFulfillmentService::class)->submit($recipient->id, 1);
    app(RecipientFulfillmentService::class)->submit($recipient->id, 2);

    expect($order->refresh()->order_status)->toBe(OrderStatus::Completed)
        ->and($recipient->refresh()->provider_response['items'][1]['reference'])->toBe($order->code.'-THE9P-OLD')
        ->and($recipient->provider_response['items'][2]['reference'])->toBe('THE9P-NEW');
    Queue::assertPushed(ReportReorderedOrderSuccess::class, 1);

    (new ReportReorderedOrderSuccess($order->id, 1))->handle(
        app(TopupProviderBalanceService::class),
        app(TopupDiscordReporterService::class),
    );

    Queue::assertPushed(SendDiscordReport::class, function (SendDiscordReport $job) use ($order, $admin): bool {
        $serialized = serialize($job);

        return $job->channel === 'provider'
            && $job->dedupeKey === "topup-order:{$order->id}:reorder:1:completed"
            && $job->details['Mã đơn'] === $order->code
            && $job->details['Lần reorder'] === 1
            && $job->details['Admin ID'] === $admin->id
            && $job->details['Số dư trước'] === '5.000.000 VND'
            && $job->details['Số dư sau'] === '4.900.000 VND'
            && $job->details['Biến động'] === '-100.000 VND'
            && ! str_contains($serialized, 'player-private')
            && ! str_contains($serialized, 'secret-key');
    });

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => $request['command'] === 'topup'
        && $request['request_id'] === $order->code.'-R001-A001-U002');
});

test('reorder rejects unpaid manual and ambiguous provider states without dispatching work', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    Queue::fake();
    Http::preventStrayRequests();

    [$unpaidOrder, , $manualProvider] = reorderableOrderFixture();
    $unpaidOrder->forceFill(['payment_status' => PaymentStatus::Pending])->save();
    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$unpaidOrder->code}", ['action' => 'reorder'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reorder');

    $manualProvider->forceFill(['slug' => 'manual'])->save();
    $unpaidOrder->forceFill([
        'payment_status' => PaymentStatus::Paid,
        'metadata' => ['provider' => ['slug' => 'manual']],
    ])->save();
    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$unpaidOrder->code}", ['action' => 'reorder'])
        ->assertUnprocessable();

    [$ambiguousOrder, $ambiguousRecipient] = reorderableOrderFixture();
    $ambiguousRecipient->forceFill([
        'provider_response' => ['items' => [
            1 => ['status' => 'completed'],
            2 => ['status' => 'processing'],
        ]],
    ])->save();
    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$ambiguousOrder->code}", ['action' => 'reorder'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reorder', 'force_reorder']);

    Queue::assertNotPushed(ProcessTopupRecipient::class);
    Http::assertNothingSent();
});

test('admin can confirm an ambiguous provider reorder while completed units stay protected', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['balance' => 5_000_000, 'currency' => 'VND'],
        ]),
    ]);

    [$order, $recipient] = reorderableOrderFixture();
    $recipient->forceFill([
        'provider_response' => ['items' => [
            1 => [
                'unit' => 1,
                'request_id' => $order->code.'-OLD-R001-U001',
                'reference' => $order->code.'-THE9P-OLD',
                'status' => 'completed',
            ],
            2 => [
                'unit' => 2,
                'request_id' => $order->code.'-OLD-R001-U002',
                'status' => 'processing',
            ],
        ]],
    ])->save();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", [
            'action' => 'reorder',
            'force_reorder' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.order_status', 'processing');

    $order->refresh();
    $recipient->refresh();

    expect(data_get($order->metadata, 'reorder.duplicate_check_overridden'))->toBeTrue()
        ->and(data_get($recipient->provider_response, 'reorder_history.0.forced'))->toBeTrue()
        ->and(data_get($recipient->provider_response, 'items.1.status'))->toBe('completed')
        ->and(data_get($recipient->provider_response, 'items.1.request_id'))->toBe($order->code.'-OLD-R001-U001')
        ->and(data_get($recipient->provider_response, 'items.2.status'))->toBe('pending')
        ->and(data_get($recipient->provider_response, 'items.2.request_id'))->toBe($order->code.'-R001-A001-U002');

    Queue::assertPushed(ProcessTopupRecipient::class, 1);
    Queue::assertPushed(
        ProcessTopupRecipient::class,
        fn (ProcessTopupRecipient $job): bool => $job->recipientId === $recipient->id && $job->unit === 2,
    );
    Queue::assertNotPushed(
        ProcessTopupRecipient::class,
        fn (ProcessTopupRecipient $job): bool => $job->recipientId === $recipient->id && $job->unit === 1,
    );

    $audit = AdminAuditLog::query()->where('action', 'order_reorder')->latest('id')->firstOrFail();
    expect(data_get($audit->new_values, 'duplicate_check_overridden'))->toBeTrue();
});

/**
 * @return array{0: Order, 1: OrderRecipient, 2: TopupProvider}
 */
function reorderableOrderFixture(): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create(['code' => '3']);
    $provider = TopupProvider::factory()->create([
        'name' => 'The9p',
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
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'game_server_id' => $server->id,
        'topup_package_id' => null,
        'topup_provider_id' => $provider->id,
        'game_account' => 'player-private',
        'quantity' => 2,
        'denomination' => 10_000,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'paid_at' => now()->subMinute(),
        'processing_at' => now()->subMinute(),
        'failed_at' => now(),
        'failure_reason' => 'Provider không đủ số dư.',
        'metadata' => ['provider' => ['slug' => 'the9p', 'service_code' => 'nr']],
    ]);
    $recipient = OrderRecipient::factory()->for($order)->create([
        'recipient_data' => ['game_account' => 'player-private'],
        'quantity' => 2,
        'status' => 'failed',
        'provider_request_id' => $order->code.'-OLD-R001',
        'provider_reference' => $order->code.'-THE9P-OLD',
        'provider_status' => 'failed',
        'provider_response' => [
            'items' => [
                1 => [
                    'unit' => 1,
                    'request_id' => $order->code.'-OLD-R001-U001',
                    'reference' => $order->code.'-THE9P-OLD',
                    'status' => 'completed',
                ],
                2 => [
                    'unit' => 2,
                    'request_id' => $order->code.'-OLD-R001-U002',
                    'reference' => null,
                    'status' => 'failed',
                    'message' => 'Insufficient balance',
                ],
            ],
        ],
        'status_check_attempts' => 2,
        'failure_reason' => 'Provider không đủ số dư.',
        'submitted_at' => now()->subMinute(),
        'last_checked_at' => now(),
        'failed_at' => now(),
    ]);

    return [$order, $recipient, $provider];
}
