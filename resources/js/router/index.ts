import { useUserStore } from '@/stores/user.store';
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import adminRouter from './modules/admin';
import affiliateRouter from './modules/affiliate';
import collaboratorRouter from './modules/collaborator';

const routes: RouteRecordRaw[] = [adminRouter, affiliateRouter, collaboratorRouter];

const routeTitles: Record<string, string> = {
    'admin.dashboard': 'Tổng quan quản trị',
    'admin.reports.index': 'Báo cáo tăng trưởng và doanh thu',
    'admin.affiliate.index': 'Quản lý Affiliate',
    'admin.affiliate.announcements': 'Thông báo Affiliate',
    'admin.support.index': 'Tin nhắn hỗ trợ',
    'admin.topup.catalog': 'Danh mục nạp game',
    'admin.topup.games': 'Danh sách game',
    'admin.topup.servers': 'Danh sách máy chủ game',
    'admin.topup.packages': 'Danh sách gói nạp game',
    'admin.topup.providers': 'Nhà cung cấp nạp game',
    'admin.topup.provider-prices': 'So sánh giá provider',
    'admin.topup.orders': 'Đơn nạp game',
    'admin.game-services.games': 'Game nhận dịch vụ',
    'admin.game-services.services': 'Quản lý dịch vụ game',
    'admin.game-services.packages': 'Gói dịch vụ game',
    'admin.game-services.orders': 'Đơn dịch vụ game',
    'admin.game-services.chats': 'Chat đơn dịch vụ game',
    'admin.game-services.announcements': 'Thông báo dashboard CTV',
    'admin.users.index': 'Quản lý người dùng',
    'admin.users.discounts': 'Quản lý user chiết khấu',
    'admin.users.show': 'Chi tiết người dùng',
    'admin.users.wallet-transaction': 'Biến động ví người dùng',
    'admin.users.wallet-transaction.show': 'Lịch sử ví người dùng',
    'admin.notifications.index': 'Thông báo hệ thống',
    'admin.notifications.create': 'Tạo thông báo',
    'admin.notifications.edit': 'Cập nhật thông báo',
    'admin.notifications.history': 'Lịch sử thông báo',
    'admin.mail.index': 'Gửi email',
    'admin.queues.index': 'Hàng đợi hệ thống',
    'admin.feedbacks.index': 'Liên hệ và góp ý',
    'admin.seo.dashboard': 'Quản trị SEO',
    'admin.seo.home': 'SEO trang chủ',
    'admin.seo.games': 'SEO từng game',
    'admin.seo.categories': 'Danh mục SEO',
    'admin.seo.posts': 'Bài viết SEO',
    'admin.seo.posts.create': 'Tạo bài viết SEO',
    'admin.seo.posts.edit': 'Cập nhật bài viết SEO',
    'admin.seo.sitemaps': 'Sitemap và index',
    'admin.settings.general': 'Cấu hình chung',
    'admin.settings.content': 'Cấu hình nội dung',
    'admin.settings.maintenance': 'Bảo trì hệ thống',
    'admin.settings.recharge': 'Cấu hình nạp tiền',
    'admin.recharge.config': 'Cấu hình nạp tiền',
    'admin.recharge.history': 'Lịch sử nạp tiền',
    'admin.error.404': 'Trang quản trị không tồn tại',
    'affiliate.notifications': 'Thông báo Affiliate',
    'affiliate.dashboard': 'Tổng quan hoa hồng',
    'affiliate.rates': 'Bảng giá chiết khấu',
    'collaborator.dashboard': 'Dashboard công việc cộng tác viên',
    'collaborator.notifications': 'Thông báo công việc cộng tác viên',
    'collaborator.revenue': 'Doanh thu công việc cộng tác viên',
    'collaborator.withdrawal': 'Rút tiền cộng tác viên',
    'collaborator.orders': 'Đơn dịch vụ được giao',
    'collaborator.chats': 'Chat đơn dịch vụ đã nhận',
};

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const routeName = typeof to.name === 'string' ? to.name : '';

    if (!routeName.startsWith('admin.') && !routeName.startsWith('affiliate.') && !routeName.startsWith('collaborator.')) {
        return true;
    }

    const userStore = useUserStore();
    const user = await userStore.bootstrap({ silent: true });

    if (!user) {
        return {
            path: '/login',
            query: {
                redirect: to.fullPath,
            },
        };
    }

    if (routeName.startsWith('admin.') && user.role !== 'admin') {
        return {
            path: '/',
        };
    }

    if (routeName.startsWith('collaborator.') && !['admin', 'ctv'].includes(user.role)) {
        return {
            path: '/',
        };
    }

    return true;
});

router.afterEach((to) => {
    const appElement = document.getElementById('app');
    const siteName = appElement?.dataset.siteName?.trim() || document.title || 'Laravel';
    const routeName = typeof to.name === 'string' ? to.name : '';
    const pageTitle = routeTitles[routeName];

    document.title = pageTitle ? `${pageTitle} | ${siteName}` : siteName;
});

router.onError((error, to) => {
    console.error(`Import component thất bại${to?.fullPath ? `: ${to.fullPath}` : ''}`, error);
});

export default router;
