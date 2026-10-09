import { BookMarked, Globe2, Layers3, ListChecks, Mail, MessagesSquare, ScrollText, Settings, Users, type LucideIcon } from 'lucide-vue-next';

export type AdminMenuChild = {
    label: string;
    href: string;
};

export type AdminMenuGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    href?: string;
    children?: AdminMenuChild[];
};

export const adminMenuGroups: AdminMenuGroup[] = [
    { key: 'services', label: 'Quản lý dịch vụ', icon: Layers3, href: '/admin/services' },
    { key: 'nro-servers', label: 'Server NRO', icon: Globe2, href: '/admin/nro/servers' },
    {
        key: 'nro-notifications',
        label: 'Thông báo game',
        icon: ListChecks,
        children: [
            { label: 'Quản lý loại thông báo', href: '/admin/nro/notification-types' },
            { label: 'Quản lý thông báo', href: '/admin/nro/notifies' },
            { label: 'Quản lý boss', href: '/admin/nro/bosses' },
        ],
    },
    { key: 'posts', label: 'Bài viết', icon: BookMarked, href: '/admin/seo/posts' },
    { key: 'categories', label: 'Danh mục', icon: Layers3, href: '/admin/seo/categories' },
    { key: 'seo-home', label: 'SEO trang chủ', icon: Globe2, href: '/admin/seo/home' },
    { key: 'sitemaps', label: 'Sitemap & index', icon: ListChecks, href: '/admin/seo/sitemaps' },
    { key: 'users', label: 'Người dùng', icon: Users, href: '/admin/users' },
    {
        key: 'settings',
        label: 'Cấu hình',
        icon: Settings,
        children: [
            { label: 'Cấu hình chung', href: '/admin/settings/general' },
            { label: 'Trang thông tin', href: '/admin/settings/content' },
            { label: 'Bảo trì', href: '/admin/settings/maintenance' },
        ],
    },
    { key: 'audit-logs', label: 'Nhật ký quản trị', icon: ScrollText, href: '/admin/audit-logs' },
    { key: 'feedbacks', label: 'Liên hệ', icon: MessagesSquare, href: '/admin/feedbacks' },
    { key: 'mail', label: 'Email', icon: Mail, href: '/admin/mail' },
    { key: 'queues', label: 'Hàng đợi', icon: ListChecks, href: '/admin/queues' },
];

export const resolveAdminPageTitle = (path: string): string => {
    for (const group of adminMenuGroups) {
        if (group.href && (path === group.href || (group.href !== '/admin' && path.startsWith(`${group.href}/`)))) {
            return group.label;
        }

        const matchedChild = group.children
            ?.slice()
            .sort((left, right) => right.href.length - left.href.length)
            .find((child) => path === child.href || path.startsWith(`${child.href}/`));

        if (matchedChild) {
            return matchedChild.label;
        }
    }

    return 'Admin';
};
