<?php

test('guest order history remains available after visiting authenticated pages', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $payment = file_get_contents($projectRoot.'/resources/views/client/orders/payment.blade.php');
    $show = file_get_contents($projectRoot.'/resources/views/client/orders/show.blade.php');
    $lookup = file_get_contents($projectRoot.'/resources/views/client/orders/lookup.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $adminScript = file_get_contents($projectRoot.'/resources/js/app.ts');

    expect($layout)
        ->toContain('data-authenticated="{{ auth()->check() ? \'true\' : \'false\' }}"')
        ->toContain('data-order-lookup-url="{{ route(\'orders.lookup\') }}"')
        ->toContain('data-order-detail-url-template="{{ route(\'orders.details\', [\'order\' => \'__ORDER__\']) }}"')
        ->and($home)
        ->not->toContain('data-guest-order-history')
        ->not->toContain('$userOrders')
        ->and($payment)
        ->toContain('@if ($order->user_id === null)')
        ->toContain('data-guest-order-code="{{ $order->code }}"')
        ->toContain('data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"')
        ->and($show)
        ->toContain('@if ($order->user_id === null)')
        ->toContain('data-guest-order-code="{{ $order->code }}"')
        ->toContain('data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"')
        ->and($lookup)
        ->toContain('Lịch sử đơn hàng')
        ->toContain('data-guest-order-history')
        ->toContain('data-guest-order-history-table')
        ->toContain('data-guest-order-history-search')
        ->toContain('data-guest-order-history-row')
        ->toContain('<table')
        ->toContain('data-order-history-unlock')
        ->toContain('data-order-detail-trigger')
        ->toContain('<x-client.order-detail-modal />')
        ->toContain("old('code', request()->query('code'))")
        ->and($script)
        ->toContain("const guestOrderHistoryKey = 'napcarot.guest-order-history.v1'")
        ->toContain('const guestOrderLifetime = 365 * 24 * 60 * 60 * 1000')
        ->toContain('const guestOrderLimit = 30')
        ->toContain("if (document.body.dataset.authenticated === 'true') return")
        ->toContain('entry.expiresAt > now')
        ->toContain('orders.filter((item) => item.code !== normalizedCode)')
        ->toContain('orderCode.textContent = entry.code')
        ->toContain('expiryTime.dateTime = new Date(entry.expiresAt).toISOString()')
        ->toContain('filterGuestOrderHistory(history)')
        ->toContain('row.dataset.guestOrderHistoryCode = entry.code')
        ->toContain("document.querySelector('[data-order-detail-modal]')")
        ->toContain('loadOrderDetail(currentDetailUrl, true)')
        ->toContain('rememberGuestOrder(order.code, order.created_at)')
        ->and($adminScript)
        ->not->toContain("window.localStorage.removeItem('napcarot.guest-order-history.v1')");
});
