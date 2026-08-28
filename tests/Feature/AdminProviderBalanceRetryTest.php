<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Jobs\ProcessTopupOrder;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('only admins can retry an order held for insufficient provider balance', function (): void {
    [$order] = providerBalanceHeldOrderFixture();

    $this->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'retry_provider_submission'])->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'retry_provider_submission'])
        ->assertForbidden();
});

test('admin can retry held order once when provider balance is sufficient', function (): void {
    [$order, $recipient] = providerBalanceHeldOrderFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake(['https://the9p.com/api/rechargews' => Http::response([
        'status' => 'success',
        'data' => ['balance' => 100_000, 'currency' => 'VND'],
    ])]);

    $this->actingAs($admin)->getJson("/api/admin-api/orders/{$order->code}")
        ->assertOk()
        ->assertJsonPath('data.can_retry_provider_submission', true)
        ->assertJsonPath('data.can_sync_provider', false);
    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'retry_provider_submission'])
        ->assertOk()
        ->assertJsonPath('data.can_retry_provider_submission', false)
        ->assertJsonPath('data.failure_reason', null);

    $order->refresh();
    $recipient->refresh();

    expect(data_get($order->metadata, 'provider_manual_review'))->toBeNull()
        ->and(data_get($order->metadata, 'provider_manual_review_history.0.code'))->toBe('provider_balance_insufficient')
        ->and($recipient->failure_reason)->toBeNull()
        ->and(data_get($recipient->provider_response, 'manual_review'))->toBeNull()
        ->and(data_get($recipient->provider_response, 'manual_review_history.0.code'))->toBe('provider_balance_insufficient')
        ->and(data_get($recipient->provider_response, 'items.1.status'))->toBe('pending');
    Queue::assertPushed(ProcessTopupOrder::class, 1);
    expect(AdminAuditLog::query()->where([
        'admin_id' => $admin->id,
        'action' => 'order_retry_provider_submission',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
    ])->exists())->toBeTrue();

    $this->actingAs($admin)
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'retry_provider_submission'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('retry_provider_submission');
    Queue::assertPushed(ProcessTopupOrder::class, 1);
    Http::assertSentCount(1);
});

test('retry keeps held order untouched while provider balance remains insufficient', function (): void {
    [$order, $recipient] = providerBalanceHeldOrderFixture();
    Queue::fake();
    Http::preventStrayRequests();
    Http::fake(['https://the9p.com/api/rechargews' => Http::response([
        'status' => 'success',
        'data' => ['balance' => 10_000, 'currency' => 'VND'],
    ])]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->putJson("/api/admin-api/orders/{$order->code}", ['action' => 'retry_provider_submission'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('retry_provider_submission');

    expect(data_get($order->refresh()->metadata, 'provider_manual_review.code'))->toBe('provider_balance_insufficient')
        ->and(data_get($recipient->refresh()->provider_response, 'manual_review.code'))->toBe('provider_balance_insufficient');
    Queue::assertNotPushed(ProcessTopupOrder::class);
    Http::assertSentCount(1);
});

/** @return array{0:Order,1:OrderRecipient} */
function providerBalanceHeldOrderFixture(): array
{
    $game = Game::factory()->create();
    $provider = TopupProvider::factory()->create([
        'name' => 'The9p',
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    $marker = [
        'code' => 'provider_balance_insufficient',
        'provider_balance' => 10_000,
        'required_balance' => 20_000,
        'currency' => 'VND',
        'marked_at' => now()->subMinute()->toISOString(),
    ];
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'topup_provider_id' => $provider->id,
        'quantity' => 2,
        'total_amount' => 24_000,
        'sale_unit_price' => 12_000,
        'provider_unit_cost' => 10_000,
        'provider_total_cost' => 20_000,
        'gross_profit' => 4_000,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
        'paid_at' => now()->subMinutes(2),
        'processing_at' => now()->subMinutes(2),
        'failure_reason' => 'Provider không đủ số dư; đơn chưa gửi sang provider.',
        'metadata' => ['provider_manual_review' => $marker],
    ]);
    $recipient = OrderRecipient::factory()->for($order)->create([
        'quantity' => 2,
        'status' => 'processing',
        'provider_status' => 'processing',
        'provider_request_id' => $order->code.'-R001',
        'provider_reference' => null,
        'submitted_at' => null,
        'failure_reason' => 'Provider không đủ số dư; đơn chưa gửi sang provider.',
        'provider_response' => [
            'manual_review' => $marker,
            'items' => [
                1 => ['unit' => 1, 'request_id' => $order->code.'-R001-U001', 'status' => 'manual_review'],
                2 => ['unit' => 2, 'request_id' => $order->code.'-R001-U002', 'status' => 'manual_review'],
            ],
        ],
    ]);

    return [$order, $recipient];
}
