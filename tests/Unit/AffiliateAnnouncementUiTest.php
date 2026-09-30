<?php

test('affiliate navigation separates overview announcements and partner home', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $adminRouter = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $affiliateRouter = file_get_contents($projectRoot.'/resources/js/router/modules/affiliate/index.ts');
    $collaboratorRouter = file_get_contents($projectRoot.'/resources/js/router/modules/collaborator/index.ts');
    $adminNavigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $affiliateLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateLayout.vue');
    $affiliateCommissionLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateCommissionLayout.vue');
    $adminPage = file_get_contents($projectRoot.'/resources/js/pages/admin/affiliate/announcements.vue');
    $affiliateHome = file_get_contents($projectRoot.'/resources/js/pages/affiliate/home.vue');
    $collaboratorNotifications = file_get_contents($projectRoot.'/resources/js/pages/affiliate/collaborator-notifications/index.vue');
    $collaboratorDashboard = file_get_contents($projectRoot.'/resources/js/pages/affiliate/collaborator-dashboard/index.vue');
    $collaboratorOrders = file_get_contents($projectRoot.'/resources/js/pages/affiliate/game-service-orders/index.vue');
    $collaboratorChats = file_get_contents($projectRoot.'/resources/js/pages/affiliate/game-service-chats/index.vue');
    $clientAffiliateService = file_get_contents($projectRoot.'/resources/js/services/client-affiliate.service.ts');
    $editor = file_get_contents($projectRoot.'/resources/js/components/shared/Editor/index.vue');

    expect($adminRouter)
        ->toContain("name: 'admin.affiliate.announcements'")
        ->toContain("name: 'admin.game-services.announcements'")
        ->toContain('@/pages/admin/affiliate/announcements.vue')
        ->and($adminNavigation)
        ->toContain("href: '/admin/affiliate'")
        ->toContain("href: '/admin/affiliate/announcements'")
        ->toContain("href: '/admin/game-services/announcements'")
        ->and($adminPage)
        ->toContain('Thông báo Dashboard CTV')
        ->toContain('Thông báo Affiliate')
        ->toContain('@click="togglePin(announcement)"')
        ->toContain('v-model="form.is_published"')
        ->toContain('<Editor v-model="form.content"')
        ->toContain('sanitizeRichText(announcement.content_html)')
        ->and($affiliateRouter)
        ->toContain("path: '/cong-tac-vien'")
        ->toContain("name: 'affiliate.dashboard'")
        ->toContain("name: 'affiliate.notifications'")
        ->toContain("name: 'affiliate.rates'")
        ->and($collaboratorRouter)
        ->toContain("path: '/dashboard'")
        ->toContain("name: 'collaborator.dashboard'")
        ->toContain("name: 'collaborator.orders'")
        ->toContain("name: 'collaborator.chats'")
        ->toContain('@/pages/affiliate/game-service-chats/index.vue')
        ->toContain('@/pages/affiliate/collaborator-notifications/index.vue')
        ->toContain("name: 'collaborator.revenue'")
        ->toContain("name: 'collaborator.withdrawal'")
        ->and($affiliateLayout)
        ->toContain('Quản lý đơn')
        ->toContain('Đơn đang chờ')
        ->toContain('Đơn đang làm')
        ->toContain('Đơn chờ duyệt')
        ->toContain('Đơn hoàn thành')
        ->toContain('Đơn thất bại')
        ->toContain('Đơn đã hủy')
        ->toContain('query: { status: item.status }')
        ->toContain('Chat đơn đã nhận')
        ->toContain('Quản lý doanh thu')
        ->toContain('summary.orders.pending')
        ->toContain('summary.unread_announcements')
        ->not->toContain('Dashboard hoa hồng')
        ->and($affiliateCommissionLayout)
        ->toContain('Tổng quan hoa hồng')
        ->toContain('Bảng giá chiết khấu')
        ->not->toContain('Công việc CTV')
        ->and($affiliateHome)
        ->toContain('Thông báo Affiliate')
        ->toContain('sanitizeRichText(announcement.content_html)')
        ->toContain('viewAnnouncement(announcement.id, announcement.is_read)')
        ->toContain('clientAffiliateService.readAnnouncement(id)')
        ->not->toContain('clientAffiliateService.readCollaboratorAnnouncement(id)')
        ->and($collaboratorNotifications)
        ->toContain('Thông báo Dashboard CTV')
        ->toContain('clientAffiliateService.collaboratorAnnouncements()')
        ->toContain('clientAffiliateService.readCollaboratorAnnouncement(id)')
        ->and($collaboratorDashboard)
        ->toContain('Dashboard tổng quan')
        ->toContain('data.orders.pending')
        ->toContain('data.revenue.held')
        ->and($collaboratorOrders)
        ->toContain('filters.search')
        ->toContain('filters.game_id')
        ->toContain('normalizeStatus(route.query.status)')
        ->toContain('applyStatusFilter')
        ->toContain('order.can_claim')
        ->toContain('order.can_chat')
        ->toContain('Đơn chờ nhận và đơn được giao')
        ->toContain('openChat(order)')
        ->and($collaboratorChats)
        ->toContain('Chat đơn đã nhận')
        ->toContain('clientAffiliateService.gameServiceOrderChats()')
        ->toContain('clientAffiliateService.gameServiceOrderThread(order.code)')
        ->toContain('clientAffiliateService.sendGameServiceOrderMessage')
        ->toContain("document.visibilityState === 'hidden'")
        ->and($clientAffiliateService)
        ->toContain('`${root}/game-service-order-chats`')
        ->and($editor)
        ->toContain('images_upload_handler: handleImageUpload')
        ->toContain("'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image emoticons");
});
