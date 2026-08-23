<?php

test('admin order page provides responsive table actions dropdown and detail modal', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');
    $modal = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/components/OrderDetailModal.vue');
    $badge = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/components/OrderStatusBadge.vue');
    $service = file_get_contents($projectRoot.'/resources/js/services/admin-topup.service.ts');

    expect($page)
        ->toContain('<table class="w-full min-w-[1180px]')
        ->toContain('Đơn hàng')
        ->toContain('Game / Tài khoản')
        ->toContain('Xử lý provider')
        ->toContain('primaryActionFor(order)')
        ->toContain('MoreVertical')
        ->toContain('<Teleport to="body">')
        ->toContain('secondaryActionsFor(activeMenuOrder)')
        ->toContain('@click.stop="runPrimaryAction(order)"')
        ->toContain('@click="openDetailModal(order)"')
        ->toContain('adminTopupService.order(order.code)')
        ->toContain('lg:hidden')
        ->toContain('mobileFiltersOpen')
        ->toContain('Không tải được danh sách đơn')
        ->toContain('Hiển thị {{ pagination.from || 0 }}')
        ->toContain('Đẩy lại thẻ lỗi')
        ->toContain('Chỉ các lượt provider đã xác nhận thất bại')
        ->toContain('Đồng bộ provider')
        ->toContain("action: 'sync_provider'")
        ->toContain('Hoàn thành thủ công')
        ->not->toContain('window.confirm')
        ->not->toContain('window.prompt')
        ->and($modal)
        ->toContain('panel-class="max-w-[920px]"')
        ->toContain('lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]')
        ->toContain("import Modal from '@/components/shared/Modal/index.vue'")
        ->toContain('displayOrder.recipients')
        ->and($badge)
        ->toContain("kind: 'payment' | 'order'")
        ->toContain('Đã thanh toán')
        ->toContain('Báo lỗi')
        ->and($service)
        ->toContain('order: (code: string)')
        ->toContain('updateOrder: (code: string')
        ->toContain('`${root}/orders/${code}`');
});
