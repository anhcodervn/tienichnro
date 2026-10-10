import { useUserStore } from '@/stores/user.store';
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import adminRouter from './modules/admin';

const routes: RouteRecordRaw[] = [adminRouter];

const routeTitles: Record<string, string> = {
    'admin.licenses': 'License keys',
    'admin.licenses.products': 'Sản phẩm / tool',
    'admin.licenses.plans': 'Gói license',
    'admin.services': 'Cấu hình dịch vụ',
    'admin.tools': 'Cấu hình công cụ',
    'admin.wallets': 'Ví & dòng tiền',
    'admin.service-packages': 'Cấu hình gói',
    'admin.nro.bosses': 'Quản lý boss',
    'admin.nro.notifies': 'Quản lý thông báo game',
    'admin.nro.notification-types': 'Quản lý loại thông báo',
    'admin.nro.servers': 'Quản lý server NRO',
    'admin.nro.zalo-receivers': 'Zalo nhận thông báo',
    'admin.users.index': 'Quản lý người dùng',
    'admin.users.show': 'Chi tiết người dùng',
    'admin.notifications.index': 'Thông báo hệ thống',
    'admin.notifications.create': 'Tạo thông báo',
    'admin.notifications.edit': 'Cập nhật thông báo',
    'admin.notifications.history': 'Lịch sử thông báo',
    'admin.mail.index': 'Gửi email',
    'admin.queues.index': 'Hàng đợi hệ thống',
    'admin.feedbacks.index': 'Liên hệ và góp ý',
    'admin.seo.home': 'SEO trang chủ',
    'admin.seo.categories': 'Danh mục SEO',
    'admin.seo.posts': 'Bài viết SEO',
    'admin.seo.posts.create': 'Tạo bài viết SEO',
    'admin.seo.posts.edit': 'Cập nhật bài viết SEO',
    'admin.seo.sitemaps': 'Sitemap và index',
    'admin.settings.general': 'Cấu hình chung',
    'admin.settings.content': 'Cấu hình nội dung',
    'admin.settings.maintenance': 'Bảo trì hệ thống',
    'admin.error.404': 'Trang quản trị không tồn tại',
};

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const routeName = typeof to.name === 'string' ? to.name : '';

    if (!routeName.startsWith('admin.')) {
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
