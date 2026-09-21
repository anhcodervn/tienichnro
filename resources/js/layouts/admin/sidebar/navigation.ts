import {
    BadgeDollarSign,
    BellRing,
    BookMarked,
    ChartNoAxesCombined,
    Gamepad2,
    Globe2,
    HandCoins,
    LayoutDashboard,
    ListChecks,
    Mail,
    MessagesSquare,
    ScrollText,
    Settings,
    ShoppingCart,
    Users,
    WalletCards,
    type LucideIcon,
} from 'lucide-vue-next';

export type AdminMenuChild = {
    label: string;
    href: string;
    platformOnly?: boolean;
    childOnly?: boolean;
    tenancyOnly?: boolean;
};

export type AdminMenuGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    href?: string;
    children?: AdminMenuChild[];
    badge?: 'support';
    platformOnly?: boolean;
    childOnly?: boolean;
    tenancyOnly?: boolean;
};

export const adminMenuGroups: AdminMenuGroup[] = [
    {
        key: 'tenant-sites',
        label: 'Website đại lý',
        icon: Globe2,
        href: '/admin/sites',
        platformOnly: true,
        tenancyOnly: true,
    },
    {
        key: 'tenant-prices',
        label: 'Giá bán website',
        icon: BadgeDollarSign,
        href: '/admin/site-prices',
        childOnly: true,
        tenancyOnly: true,
    },
    {
        key: 'dashboard',
        label: 'Dashboard',
        icon: LayoutDashboard,
        href: '/admin',
    },
    {
        key: 'reports',
        label: 'Báo cáo doanh thu',
        icon: ChartNoAxesCombined,
        href: '/admin/reports',
    },
    {
        key: 'audit-logs',
        label: 'Nhật ký quản trị',
        icon: ScrollText,
        href: '/admin/audit-logs',
    },
    {
        key: 'affiliate',
        label: 'Affiliate',
        icon: HandCoins,
        children: [
            {
                label: 'Tổng quan',
                href: '/admin/affiliate',
            },
            {
                label: 'Thông báo',
                href: '/admin/affiliate/announcements',
            },
        ],
    },
    {
        key: 'support',
        label: 'Tin nhắn hỗ trợ',
        icon: MessagesSquare,
        href: '/admin/support',
        badge: 'support',
    },
    {
        key: 'topup-catalog',
        label: 'Danh mục nạp game',
        icon: Gamepad2,
        children: [
            {
                label: 'Game',
                href: '/admin/topup/games',
            },
            {
                label: 'Máy chủ',
                href: '/admin/topup/servers',
            },
            {
                label: 'Gói nạp',
                href: '/admin/topup/packages',
            },
            {
                label: 'Gói nạp Global',
                href: '/admin/topup/global-packages',
            },
            {
                label: 'Bảng thực nhận game',
                href: '/admin/topup/global-rewards',
            },
        ],
        platformOnly: true,
    },
    {
        key: 'topup-providers',
        label: 'Nhà cung cấp',
        icon: HandCoins,
        children: [
            {
                label: 'Danh sách provider',
                href: '/admin/topup/providers',
            },
            {
                label: 'So sánh giá provider',
                href: '/admin/topup/provider-prices',
            },
        ],
        platformOnly: true,
    },
    {
        key: 'topup-orders',
        label: 'Đơn nạp game',
        icon: ShoppingCart,
        href: '/admin/topup/orders',
    },
    {
        key: 'recharge',
        label: 'Quản lý nạp tiền',
        icon: WalletCards,
        children: [
            {
                label: 'Cấu hình nạp tiền',
                href: '/admin/recharge/config',
            },
            {
                label: 'Lịch sử nạp tiền',
                href: '/admin/recharge/history',
            },
        ],
    },
    {
        key: 'users',
        label: 'Quản lý người dùng',
        icon: Users,
        children: [
            {
                label: 'Danh sách thành viên',
                href: '/admin/users',
            },
            {
                label: 'Lịch sử dòng tiền',
                href: '/admin/users/wallet-transactions',
            },
        ],
    },
    {
        key: 'notifications',
        label: 'Thông báo hệ thống',
        icon: BellRing,
        children: [
            {
                label: 'Tạo thông báo mới',
                href: '/admin/notifications/create',
            },
            {
                label: 'Danh sách thông báo',
                href: '/admin/notifications',
            },
        ],
        platformOnly: true,
    },
    {
        key: 'seo-management',
        label: 'Quản trị SEO',
        icon: BookMarked,
        children: [
            {
                label: 'Tổng quan SEO',
                href: '/admin/seo',
            },
            {
                label: 'Danh mục SEO',
                href: '/admin/seo/categories',
            },
            {
                label: 'Bài viết SEO',
                href: '/admin/seo/posts',
            },
            {
                label: 'Tạo bài viết',
                href: '/admin/seo/posts/create',
            },
            {
                label: 'Sitemap & index',
                href: '/admin/seo/sitemaps',
            },
        ],
        platformOnly: true,
    },
    {
        key: 'settings',
        label: 'Cấu hình hệ thống',
        icon: Settings,
        children: [
            {
                label: 'Cấu hình chung',
                href: '/admin/settings/general',
            },
            {
                label: 'Cấu hình nội dung',
                href: '/admin/settings/content',
            },
            {
                label: 'Cấu hình Bio',
                href: '/admin/settings/bio',
            },
        ],
    },
    {
        key: 'mail',
        label: 'Gửi email',
        icon: Mail,
        href: '/admin/mail',
        platformOnly: true,
    },
    {
        key: 'queues',
        label: 'Quản lý queue',
        icon: ListChecks,
        href: '/admin/queues',
        platformOnly: true,
    },
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
