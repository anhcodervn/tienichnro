<?php

test('admin revenue reporting is split into topup and game service pages', function (): void {
    $root = dirname(__DIR__, 2);
    $navigation = file_get_contents($root.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $router = file_get_contents($root.'/resources/js/router/modules/admin/index.ts');
    $topup = file_get_contents($root.'/resources/js/pages/admin/reports/index.vue');
    $gameServices = file_get_contents($root.'/resources/js/pages/admin/reports/game-services.vue');
    $service = file_get_contents($root.'/resources/js/services/admin-reporting.service.ts');

    expect($navigation)
        ->toContain("label: 'Báo cáo doanh thu'")
        ->toContain("label: 'Doanh thu nạp game', href: '/admin/reports/topup'")
        ->toContain("label: 'Doanh thu dịch vụ', href: '/admin/reports/game-services', platformOnly: true")
        ->and($router)
        ->toContain("redirect: { name: 'admin.reports.topup' }")
        ->toContain("name: 'admin.reports.topup'")
        ->toContain("name: 'admin.reports.game-services'")
        ->and($topup)
        ->toContain('Tổng đơn được tạo')
        ->toContain('Đơn thành công')
        ->toContain('Đơn thất bại')
        ->toContain('report.value.summary.revenue')
        ->and($gameServices)
        ->toContain('CTV đang bị giữ khi làm đơn')
        ->toContain('Hoàn thành, chưa kết toán')
        ->toContain('Tổng tiền chưa kết toán')
        ->toContain('Đã kết toán cho CTV')
        ->toContain('Sau khi trả CTV')
        ->toContain('report.financials.net_profit')
        ->and($service)
        ->toContain("'/api/admin-api/reports/topup'")
        ->toContain("'/api/admin-api/reports/game-services'");
});
