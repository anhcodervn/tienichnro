<?php

test('game service order history uses accessible chat and pending cancel icon actions', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $view = file_get_contents($projectRoot.'/resources/views/client/account/game-service-orders/index.blade.php');
    $icons = file_get_contents($projectRoot.'/public/assets/icon/boxicons/fonts/basic/boxicons.min.css');
    $clientScript = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($view)
        ->toContain('bx bx-message-circle-dots text-lg')
        ->toContain('bx bx-x-circle text-lg')
        ->toContain("@if (\$order->status === 'pending')")
        ->toContain('data-game-service-order-cancel-form')
        ->toContain('aria-label="Chat đơn {{ $order->code }}"')
        ->toContain('aria-label="Hủy đơn {{ $order->code }}"')
        ->not->toContain('bx-message-rounded-dots')
        ->and($icons)
        ->toContain('.bx-message-circle-dots:before')
        ->toContain('.bx-x-circle:before')
        ->and($clientScript)
        ->toContain("document.querySelectorAll('[data-game-service-order-cancel-form]')")
        ->toContain("title: 'Hủy đơn dịch vụ?'");
});
