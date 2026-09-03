<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Events\AdminTopupOrderUpdated;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Features\Topup\Services\OrderStatusService;
use App\Features\Topup\Support\OrderRealtimeChannel;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;

test('a newly created order dispatches the private admin realtime event', function (): void {
    Event::fake([AdminTopupOrderUpdated::class]);

    $order = Order::factory()->create();

    Event::assertDispatched(
        AdminTopupOrderUpdated::class,
        fn (AdminTopupOrderUpdated $event): bool => $event->orderId === $order->id && $event->code === $order->code,
    );
});

test('a status transition dispatches a realtime order event', function (): void {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Paid,
    ]);

    Event::fake([OrderStatusUpdated::class, AdminTopupOrderUpdated::class]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Processing);

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order): bool {
        return $event->orderStatus === OrderStatus::Processing->value
            && $event->paymentStatus === PaymentStatus::Paid->value
            && $event->channelName === OrderRealtimeChannel::for($order);
    });
    Event::assertDispatched(AdminTopupOrderUpdated::class, fn (AdminTopupOrderUpdated $event): bool => $event->code === $order->code);
});

test('provider reference updates only dispatch the private admin realtime event', function (): void {
    $order = Order::factory()->create();

    Event::fake([OrderStatusUpdated::class, AdminTopupOrderUpdated::class]);

    $order->forceFill(['provider_reference' => 'provider-reference-123'])->save();

    Event::assertNotDispatched(OrderStatusUpdated::class);
    Event::assertDispatched(AdminTopupOrderUpdated::class);
});

test('a recipient provider status update dispatches the same realtime order event', function (): void {
    $order = Order::factory()->create();
    $recipient = OrderRecipient::factory()->for($order)->create();

    Event::fake([OrderStatusUpdated::class, AdminTopupOrderUpdated::class]);

    $recipient->update(['status' => 'processing', 'provider_status' => 'pending']);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->channelName === OrderRealtimeChannel::for($order)
        && $event->recipientSummary['processing'] === 1);
    Event::assertDispatched(AdminTopupOrderUpdated::class, fn (AdminTopupOrderUpdated $event): bool => $event->code === $order->code);
});

test('provider bookkeeping without a visible status change does not broadcast', function (): void {
    $order = Order::factory()->create();
    $recipient = OrderRecipient::factory()->for($order)->create([
        'status' => 'processing',
        'provider_status' => 'processing',
    ]);

    Event::fake([OrderStatusUpdated::class, AdminTopupOrderUpdated::class]);

    $recipient->update([
        'provider_reference' => 'provider-reference-123',
        'provider_response' => ['status_check_attempts' => 2],
        'last_checked_at' => now(),
    ]);

    Event::assertNotDispatched(OrderStatusUpdated::class);
    Event::assertDispatched(AdminTopupOrderUpdated::class);
});

test('admin order event uses the private admin channel and sends the row snapshot', function (): void {
    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Failed,
        'provider_reference' => 'THE9P-REF',
        'failure_reason' => 'Provider hết số dư',
    ]);

    $event = new AdminTopupOrderUpdated($order);
    $channels = $event->broadcastOn();

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->toBeInstanceOf(ShouldRescue::class)
        ->and($channels)->toHaveCount(2)
        ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe("private-admin.sites.{$order->tenant_id}.topup.orders")
        ->and($channels[1])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[1]->name)->toBe('private-admin.platform.topup.orders')
        ->and($event->broadcastAs())->toBe('admin.topup.order.updated')
        ->and($event->broadcastWith())->toMatchArray([
            'id' => $order->id,
            'code' => $order->code,
            'payment_status' => 'paid',
            'order_status' => 'failed',
            'provider_reference' => 'THE9P-REF',
            'failure_reason' => 'Provider hết số dư',
        ]);
});

test('only admins can authorize the private topup order channel', function (): void {
    $tenantId = Tenant::query()->where('is_main', true)->value('id');
    $payload = ['socket_id' => '1234.5678', 'channel_name' => "private-admin.sites.{$tenantId}.topup.orders"];

    $this->actingAs(User::factory()->create())
        ->postJson('/broadcasting/auth', $payload)
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/broadcasting/auth', $payload)
        ->assertSuccessful();

    $platformPayload = ['socket_id' => '1234.5678', 'channel_name' => 'private-admin.platform.topup.orders'];

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/broadcasting/auth', $platformPayload)
        ->assertSuccessful();

    $child = Tenant::factory()->create();
    TenantDomain::factory()->for($child)->create(['domain' => 'child-realtime.test']);
    $childAdmin = User::factory()->create(['tenant_id' => $child->id, 'role' => 'admin']);

    $this->actingAs($childAdmin)
        ->postJson('http://child-realtime.test/broadcasting/auth', $platformPayload)
        ->assertForbidden();
});

test('the realtime event uses an opaque channel and exposes only status data', function (): void {
    $order = Order::factory()->create([
        'email' => 'customer@example.com',
        'normalized_email' => 'customer@example.com',
        'game_account' => 'secret-player',
        'provider_reference' => 'provider-secret-reference',
        'failure_reason' => 'private provider failure',
        'total_amount' => 450000,
        'paid_at' => now(),
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
        'processing_at' => now(),
    ]);

    $event = new OrderStatusUpdated($order);
    $channel = $event->broadcastOn();
    $payload = $event->broadcastWith();
    $serializedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

    expect($event)
        ->toBeInstanceOf(ShouldBroadcastNow::class)
        ->toBeInstanceOf(ShouldDispatchAfterCommit::class)
        ->toBeInstanceOf(ShouldRescue::class)
        ->and($channel)->toBeInstanceOf(Channel::class)
        ->and($channel->name)->toMatch('/^orders\.[a-f0-9]{64}$/')
        ->not->toContain($order->code)
        ->and($event->broadcastAs())->toBe('order.status.updated')
        ->and(array_keys($payload))->toBe([
            'payment_status',
            'order_status',
            'paid_at',
            'processing_at',
            'completed_at',
            'failed_at',
            'cancelled_at',
            'updated_at',
            'recipient_summary',
        ])
        ->and($serializedPayload)
        ->not->toContain('customer@example.com')
        ->not->toContain('secret-player')
        ->not->toContain('provider-secret-reference')
        ->not->toContain('private provider failure')
        ->not->toContain('450000');
});
