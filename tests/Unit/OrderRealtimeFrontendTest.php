<?php

test('pending order pages use Reverb instead of polling', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $show = file_get_contents($projectRoot.'/resources/views/client/orders/show.blade.php');
    $payment = file_get_contents($projectRoot.'/resources/views/client/orders/payment.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $event = file_get_contents($projectRoot.'/app/Features/Topup/Events/OrderStatusUpdated.php');
    $observer = file_get_contents($projectRoot.'/app/Features/Topup/Observers/OrderObserver.php');
    $adminOrders = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $adminHome = file_get_contents($projectRoot.'/resources/js/pages/admin/home/index.vue');
    $adminEvent = file_get_contents($projectRoot.'/app/Features/Topup/Events/AdminTopupOrderUpdated.php');
    $channels = file_get_contents($projectRoot.'/routes/channels.php');

    expect($show)
        ->toContain('data-order-realtime-channel="{{ $realtimeChannel }}"')
        ->toContain('data-order-realtime-connection')
        ->toContain('data-order-payment-status-text')
        ->toContain('data-order-status-text')
        ->toContain('data-order-recipient-summary')
        ->and($payment)
        ->toContain('data-order-realtime-channel="{{ $realtimeChannel }}"')
        ->toContain('data-order-realtime-connection')
        ->toContain('data-order-page="payment"')
        ->toContain('data-order-show-url="{{ route(')
        ->not->toContain('data-order-status-url')
        ->and($script)
        ->toContain("import Echo from 'laravel-echo'")
        ->toContain("import Pusher from 'pusher-js'")
        ->toContain("broadcaster: 'reverb'")
        ->toContain("listen('.order.status.updated'")
        ->toContain('updateOrderRealtimeState')
        ->toContain("querySelector('[data-order-recipient-summary]')")
        ->toContain('window.location.assign(orderUrl)')
        ->toContain('window.location.reload()')
        ->not->toContain('data-order-status-url')
        ->not->toContain('window.setInterval')
        ->not->toContain('axios.get')
        ->and($event)
        ->toContain('ShouldBroadcastNow')
        ->toContain('ShouldDispatchAfterCommit')
        ->toContain("return 'order.status.updated'")
        ->and($observer)
        ->toContain('OrderStatusUpdated::dispatch($order)');

    expect($adminOrders)
        ->toContain("import { echo } from '@laravel/echo-vue'")
        ->toContain("const realtimeChannelName = 'admin.topup.orders'")
        ->toContain("const realtimeEventName = '.admin.topup.order.updated'")
        ->toContain('applyRealtimeSnapshot(event)')
        ->toContain('flushRealtimeRefresh()')
        ->toContain('realtimeChannel.subscribed(')
        ->toContain('.stopListening(realtimeEventName, handleRealtimeOrderUpdated)')
        ->toContain('echo().leave(realtimeChannelName)')
        ->not->toContain('window.setInterval')
        ->and($adminEvent)
        ->toContain('ShouldBroadcastNow')
        ->toContain('ShouldDispatchAfterCommit')
        ->toContain("new PrivateChannel('admin.topup.orders')")
        ->toContain("return 'admin.topup.order.updated'")
        ->and($channels)
        ->toContain("Broadcast::channel('admin.topup.orders'")
        ->toContain('return $user->role === \'admin\'');

    expect($adminHome)
        ->toContain("const realtimeChannelName = 'admin.topup.orders'")
        ->toContain("const realtimeEventName = '.admin.topup.order.updated'")
        ->toContain('handleRealtimeOrderUpdated')
        ->toContain('realtimeChannel.subscribed(')
        ->toContain('.stopListening(realtimeEventName, handleRealtimeOrderUpdated)')
        ->toContain('echo().leave(realtimeChannelName)')
        ->not->toContain('window.setInterval');
});
