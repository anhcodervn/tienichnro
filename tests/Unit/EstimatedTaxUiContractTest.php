<?php

test('admin tax settings orders and reports expose estimated tax language', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $settings = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/TaxSettings.vue');
    $orders = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $orderDetail = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/components/OrderDetailModal.vue');
    $reports = file_get_contents($projectRoot.'/resources/js/pages/admin/reports/index.vue');

    expect($settings)
        ->toContain('Thuế dự kiến')
        ->toContain('Theo doanh thu bán ra')
        ->toContain('Theo lợi nhuận — sẽ hỗ trợ sau')
        ->toContain('không phải số thuế đã kê khai hoặc đã nộp');

    expect($orders)
        ->toContain('Tài chính dự kiến')
        ->toContain('Lãi ròng dự kiến')
        ->toContain('Đơn cũ chưa có snapshot');

    expect($orderDetail)
        ->toContain('Thuế GTGT dự kiến')
        ->toContain('Thuế TNCN dự kiến')
        ->toContain('Tổng thuế dự kiến')
        ->toContain('Lãi ròng dự kiến');

    expect($reports)
        ->toContain('Tổng thuế dự kiến')
        ->toContain('Lãi ròng dự kiến')
        ->toContain('đơn cũ chưa có snapshot thuế');
});
