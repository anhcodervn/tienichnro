<?php

test('admin topup order ui exposes cancel and refund through its dedicated action', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $modal = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/components/OrderDetailModal.vue');
    $types = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/types.ts');

    expect($page)
        ->toContain('order.can_cancel_refund')
        ->toContain("action: 'cancel', label: 'Huỷ đơn không hoàn tiền'")
        ->toContain("action: 'cancel_refund'")
        ->toContain("actions.push({ action: 'complete', label: 'Hoàn thành thủ công'")
        ->toContain('Huỷ đơn không hoàn tiền?')
        ->toContain('không hoàn tiền vào ví khách hàng')
        ->toContain('Huỷ đơn hoàn tiền')
        ->toContain('Toàn bộ ${formatMoney(order.total_amount)}')
        ->and($modal)
        ->toContain('displayOrder.can_cancel_refund')
        ->toContain("emit('action', 'cancel')")
        ->toContain('Huỷ đơn không hoàn tiền')
        ->toContain("emit('action', 'cancel_refund')")
        ->toContain('Huỷ đơn hoàn tiền')
        ->toContain("displayOrder.can_cancel_refund || (displayOrder.order_status === 'cancelled' && displayOrder.payment_status === 'paid')")
        ->toContain("emit('action', 'complete')")
        ->toContain('Hoàn thành thủ công')
        ->and($types)
        ->toContain("| 'cancel_refund';")
        ->toContain('can_cancel_refund: boolean;');
});
