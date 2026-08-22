<?php

test('guest order history is a one year local bookmark that clears after login', function (): void {
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
        ->and($home)
        ->toContain('data-guest-order-history hidden')
        ->toContain('data-guest-order-history-list')
        ->toContain('data-guest-order-history-item')
        ->and($payment)
        ->toContain('@if ($order->user_id === null)')
        ->toContain('data-guest-order-code="{{ $order->code }}"')
        ->toContain('data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"')
        ->and($show)
        ->toContain('@if ($order->user_id === null)')
        ->toContain('data-guest-order-code="{{ $order->code }}"')
        ->toContain('data-guest-order-created-at="{{ $order->created_at?->toISOString() }}"')
        ->and($lookup)
        ->toContain("old('code', request()->query('code'))")
        ->and($script)
        ->toContain("const guestOrderHistoryKey = 'napcarot.guest-order-history.v1'")
        ->toContain('const guestOrderLifetime = 365 * 24 * 60 * 60 * 1000')
        ->toContain('const guestOrderLimit = 30')
        ->toContain("if (document.body.dataset.authenticated === 'true')")
        ->toContain('removeGuestOrderHistory()')
        ->toContain('entry.expiresAt > now')
        ->toContain('orders.filter((item) => item.code !== code)')
        ->toContain('orderCode.textContent = entry.code')
        ->toContain("url.searchParams.set('code', entry.code)")
        ->and($adminScript)
        ->toContain("window.localStorage.removeItem('napcarot.guest-order-history.v1')");
});
