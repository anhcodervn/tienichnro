<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Features\Topup\Services\OrderStatusService;
use App\Features\Topup\Support\OrderRealtimeChannel;
use App\Models\Order;
use App\Models\OrderRecipient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\Event;

test('a status transition dispatches a realtime order event', function (): void {
    $order = Order::factory()->create([
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Paid,
    ]);

    Event::fake([OrderStatusUpdated::class]);

    app(OrderStatusService::class)->transition($order, OrderStatus::Processing);

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order): bool {
        return $event->orderStatus === OrderStatus::Processing->value
            && $event->paymentStatus === PaymentStatus::Paid->value
            && $event->channelName === OrderRealtimeChannel::for($order);
    });
});

test('unrelated order updates do not dispatch a realtime event', function (): void {
    $order = Order::factory()->create();

    Event::fake([OrderStatusUpdated::class]);

    $order->forceFill(['provider_reference' => 'provider-reference-123'])->save();

    Event::assertNotDispatched(OrderStatusUpdated::class);
});

test('a recipient provider status update dispatches the same realtime order event', function (): void {
    $order = Order::factory()->create();
    $recipient = OrderRecipient::factory()->for($order)->create();

    Event::fake([OrderStatusUpdated::class]);

    $recipient->update(['status' => 'processing', 'provider_status' => 'pending']);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->channelName === OrderRealtimeChannel::for($order)
        && $event->recipientSummary['processing'] === 1);
});

test('provider bookkeeping without a visible status change does not broadcast', function (): void {
    $order = Order::factory()->create();
    $recipient = OrderRecipient::factory()->for($order)->create([
        'status' => 'processing',
        'provider_status' => 'processing',
    ]);

    Event::fake([OrderStatusUpdated::class]);

    $recipient->update([
        'provider_reference' => 'provider-reference-123',
        'provider_response' => ['status_check_attempts' => 2],
        'last_checked_at' => now(),
    ]);

    Event::assertNotDispatched(OrderStatusUpdated::class);
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
