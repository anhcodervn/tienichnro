<?php

test('admin topup order ui exposes cancel and refund through its dedicated action', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $modal = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/components/OrderDetailModal.vue');
    $types = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/types.ts');

    expect($page)
        ->toContain('order.can_cancel_refund')
        ->toContain("action: 'cancel_refund'")
        ->toContain('Huỷ đơn hoàn tiền')
        ->toContain('Toàn bộ ${formatMoney(order.total_amount)}')
        ->and($modal)
        ->toContain('displayOrder.can_cancel_refund')
        ->toContain("emit('action', 'cancel_refund')")
        ->toContain('Huỷ đơn hoàn tiền')
        ->and($types)
        ->toContain("| 'cancel_refund';")
        ->toContain('can_cancel_refund: boolean;');
});
