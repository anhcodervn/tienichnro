<?php

test('game service order history uses accessible chat and pending cancel icon actions', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $view = file_get_contents($projectRoot.'/resources/views/client/account/game-service-orders/index.blade.php');
    $serviceView = file_get_contents($projectRoot.'/resources/views/client/game-services/service.blade.php');
    $chatView = file_get_contents($projectRoot.'/resources/views/client/account/game-service-orders/chat.blade.php');
    $statusComponent = file_get_contents($projectRoot.'/resources/views/components/client/game-service-order-status.blade.php');
    $icons = file_get_contents($projectRoot.'/public/assets/icon/boxicons/fonts/basic/boxicons.min.css');
    $clientScript = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($view)
        ->toContain('<x-client.game-service-order-status :status="$order->status" />')
        ->toContain("in_array(\$order->status, ['failed', 'cancelled'], true)")
        ->toContain('Chat xem vấn đề')
        ->toContain('bx bx-message-circle-dots text-lg')
        ->toContain('bx bx-x-circle text-lg')
        ->toContain("@if (\$order->status === 'pending')")
        ->toContain('data-game-service-order-cancel-form')
        ->toContain('aria-label="Chat đơn {{ $order->code }}"')
        ->toContain('aria-label="Hủy đơn {{ $order->code }}"')
        ->not->toContain('bx-message-rounded-dots')
        ->and($serviceView)
        ->toContain('<x-client.game-service-order-status :status="$order->status" />')
        ->and($chatView)
        ->toContain('<x-client.game-service-order-status :status="$order->status" />')
        ->toContain('data-game-service-order-chat-support')
        ->toContain('bx bx-message-circle-dots text-2xl')
        ->not->toContain('bx-message-rounded-dots')
        ->and($statusComponent)
        ->toContain("'pending' => ['Chờ duyệt'")
        ->toContain("'processing', 'review' => ['Đang thực hiện'")
        ->toContain("'completed' => ['Hoàn thành'")
        ->toContain("'failed', 'cancelled' => ['Trả về/hoàn tiền'")
        ->and($icons)
        ->toContain('.bx-message-circle-dots:before')
        ->toContain('.bx-x-circle:before')
        ->and($clientScript)
        ->toContain("document.querySelectorAll('[data-game-service-order-cancel-form]')")
        ->toContain("title: 'Hủy đơn dịch vụ?'");
});
