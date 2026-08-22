<?php

test('admin order page exposes a guarded reorder action using the order route key', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $service = file_get_contents($projectRoot.'/resources/js/services/admin-topup.service.ts');

    expect($page)
        ->toContain('v-if="order.can_reorder"')
        ->toContain('adminTopupService.updateOrder(order.code, action')
        ->toContain("act(order, 'reorder')")
        ->toContain('Xác nhận đã nạp tiền vào provider')
        ->toContain('Hệ thống chỉ gửi lại các lượt đã thất bại')
        ->toContain('RotateCcw')
        ->toContain('actingCode === order.code')
        ->and($service)
        ->toContain('updateOrder: (code: string')
        ->toContain('`${root}/orders/${code}`');
});
