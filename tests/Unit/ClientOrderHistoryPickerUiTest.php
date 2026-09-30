<?php

test('mobile history navigation opens a chooser for topup and service orders', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $picker = file_get_contents($projectRoot.'/resources/views/components/client/order-history-picker-modal.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');

    expect($layout)
        ->toContain('data-order-history-picker-open')
        ->toContain('aria-controls="order-history-picker-modal"')
        ->toContain('data-mobile-nav-item="history"')
        ->toContain('<x-client.order-history-picker-modal')
        ->and($picker)
        ->toContain('data-order-history-picker-modal')
        ->toContain('data-order-history-picker-panel')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('Lịch sử nạp')
        ->toContain('Lịch sử dịch vụ')
        ->toContain('{{ $topupUrl }}')
        ->toContain('{{ $serviceUrl }}')
        ->and($script)
        ->toContain("document.querySelector('[data-order-history-picker-modal]')")
        ->toContain("document.querySelectorAll('[data-order-history-picker-open]')")
        ->toContain("document.body.classList.add('client-game-picker-open')")
        ->toContain("orderHistoryPickerModal.querySelectorAll('[data-order-history-picker-close]')")
        ->toContain("if (event.key === 'Escape')")
        ->toContain("if (event.key !== 'Tab') return");
});
