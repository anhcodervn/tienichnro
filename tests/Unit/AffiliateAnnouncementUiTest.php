<?php

test('affiliate navigation separates overview announcements and partner home', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $adminRouter = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $affiliateRouter = file_get_contents($projectRoot.'/resources/js/router/modules/affiliate/index.ts');
    $adminNavigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $affiliateLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateLayout.vue');
    $adminPage = file_get_contents($projectRoot.'/resources/js/pages/admin/affiliate/announcements.vue');
    $affiliateHome = file_get_contents($projectRoot.'/resources/js/pages/affiliate/home.vue');
    $collaboratorDashboard = file_get_contents($projectRoot.'/resources/js/pages/affiliate/collaborator-dashboard/index.vue');
    $collaboratorOrders = file_get_contents($projectRoot.'/resources/js/pages/affiliate/game-service-orders/index.vue');
    $editor = file_get_contents($projectRoot.'/resources/js/components/shared/Editor/index.vue');

    expect($adminRouter)
        ->toContain("name: 'admin.affiliate.announcements'")
        ->toContain('@/pages/admin/affiliate/announcements.vue')
        ->and($adminNavigation)
        ->toContain("href: '/admin/affiliate'")
        ->toContain("href: '/admin/affiliate/announcements'")
        ->and($adminPage)
        ->toContain('Thông báo cộng tác viên')
        ->toContain('@click="togglePin(announcement)"')
        ->toContain('v-model="form.is_published"')
        ->toContain('<Editor v-model="form.content"')
        ->toContain('sanitizeRichText(announcement.content_html)')
        ->and($affiliateRouter)
        ->toContain("name: 'affiliate.collaborator.dashboard'")
        ->toContain("name: 'affiliate.notifications'")
        ->toContain("name: 'affiliate.revenue'")
        ->toContain("name: 'affiliate.withdrawal'")
        ->and($affiliateLayout)
        ->toContain('Quản lý đơn')
        ->toContain('Quản lý doanh thu')
        ->toContain('summary.orders.pending')
        ->toContain('summary.unread_announcements')
        ->and($affiliateHome)
        ->toContain('Thông báo từ quản trị viên')
        ->toContain('sanitizeRichText(announcement.content_html)')
        ->toContain('viewAnnouncement(announcement.id, announcement.is_read)')
        ->toContain('clientAffiliateService.readAnnouncement(id)')
        ->and($collaboratorDashboard)
        ->toContain('Dashboard tổng quan')
        ->toContain('data.orders.pending')
        ->toContain('data.revenue.held')
        ->and($collaboratorOrders)
        ->toContain("status: 'pending'")
        ->toContain('filters.search')
        ->toContain('filters.game_id')
        ->toContain('openChat(order)')
        ->and($editor)
        ->toContain('images_upload_handler: handleImageUpload')
        ->toContain("'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image emoticons");
});
