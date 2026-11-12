import {
    Globe2,
    KeyRound,
    Layers3,
    LayoutDashboard,
    ListChecks,
    Mail,
    MessagesSquare,
    ScanLine,
    Settings,
    Users,
    Wallet,
    type LucideIcon,
} from 'lucide-vue-next';

export const adminMenuSections = [
    { key: 'customers', label: 'Khách hàng & giao dịch', description: 'Tài khoản, số dư và quyền sử dụng.' },
    { key: 'services', label: 'Công cụ & trò chơi', description: 'Vận hành công cụ, captcha và dữ liệu game.' },
    { key: 'content', label: 'Nội dung & hỗ trợ', description: 'Bài viết, tìm kiếm và phản hồi người dùng.' },
    { key: 'system', label: 'Hệ thống', description: 'Cấu hình website, email và hàng đợi.' },
] as const;

export type AdminMenuChild = { label: string; href: string };
export type AdminMenuGroup = {
    key: string;
    label: string;
    icon: LucideIcon;
    section?: (typeof adminMenuSections)[number]['key'];
    href?: string;
    children?: AdminMenuChild[];
};

export const adminMenuGroups: AdminMenuGroup[] = [
    { key: 'overview', label: 'Tổng quan', icon: LayoutDashboard, href: '/admin' },
    { key: 'users', label: 'Người dùng', icon: Users, section: 'customers', href: '/admin/users' },
    {
        key: 'wallet',
        label: 'Ví & giao dịch',
        icon: Wallet,
        section: 'customers',
        children: [
            { label: 'Ví chính', href: '/admin/wallets' },
            { label: 'Token captcha', href: '/admin/wallets/token-captcha' },
        ],
    },
    {
        key: 'licenses',
        label: 'License',
        icon: KeyRound,
        section: 'customers',
        children: [
            { label: 'License keys', href: '/admin/licenses' },
            { label: 'Client Game', href: '/admin/licenses/client-game' },
            { label: 'Sản phẩm / tool', href: '/admin/licenses/products' },
            { label: 'Gói license', href: '/admin/licenses/plans' },
        ],
    },
    { key: 'tools', label: 'Cấu hình công cụ', icon: Layers3, section: 'services', href: '/admin/tools' },
    {
        key: 'captcha',
        label: 'Giải captcha',
        icon: ScanLine,
        section: 'services',
        children: [{ label: 'Lịch sử giải', href: '/admin/captcha/history' }],
    },
    { key: 'nro-servers', label: 'Server NRO', icon: Globe2, section: 'services', href: '/admin/nro/servers' },
    {
        key: 'nro-notifications',
        label: 'Thông báo game',
        icon: ListChecks,
        section: 'services',
        children: [
            { label: 'Loại thông báo', href: '/admin/nro/notification-types' },
            { label: 'Danh sách thông báo', href: '/admin/nro/notifies' },
            { label: 'Boss', href: '/admin/nro/bosses' },
            { label: 'Zalo nhận thông báo', href: '/admin/nro/zalo-receivers' },
        ],
    },
    {
        key: 'seo',
        label: 'SEO',
        icon: Globe2,
        section: 'content',
        children: [
            { label: 'Bài viết', href: '/admin/seo/posts' },
            { label: 'Danh mục', href: '/admin/seo/categories' },
            { label: 'SEO trang chủ', href: '/admin/seo/home' },
            { label: 'Sitemap & index', href: '/admin/seo/sitemaps' },
        ],
    },
    { key: 'feedbacks', label: 'Liên hệ & góp ý', icon: MessagesSquare, section: 'content', href: '/admin/feedbacks' },
    {
        key: 'settings',
        label: 'Cấu hình website',
        icon: Settings,
        section: 'system',
        children: [
            { label: 'Cấu hình chung', href: '/admin/settings/general' },
            { label: 'Trang thông tin', href: '/admin/settings/content' },
            { label: 'Bảo trì', href: '/admin/settings/maintenance' },
        ],
    },
    { key: 'mail', label: 'Email', icon: Mail, section: 'system', href: '/admin/mail' },
    { key: 'queues', label: 'Hàng đợi', icon: ListChecks, section: 'system', href: '/admin/queues' },
];

const normalizeSearch = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLocaleLowerCase('vi')
        .trim();

export const filterAdminMenu = (query: string): AdminMenuGroup[] => {
    const keyword = normalizeSearch(query);
    if (!keyword) return adminMenuGroups;
    return adminMenuGroups.flatMap((group) => {
        if (normalizeSearch(group.label).includes(keyword)) return [group];
        const children = group.children?.filter((child) => normalizeSearch(child.label).includes(keyword));
        return children?.length ? [{ ...group, children }] : [];
    });
};

export const resolveAdminNavigation = (path: string): { group: AdminMenuGroup; label: string; href: string } | null => {
    const matches = adminMenuGroups.flatMap((group) => [
        ...(group.href ? [{ group, label: group.label, href: group.href }] : []),
        ...(group.children ?? []).map((child) => ({ group, ...child })),
    ]);
    return (
        matches
            .sort((left, right) => right.href.length - left.href.length)
            .find((item) => path === item.href || (item.href !== '/admin' && path.startsWith(item.href + '/'))) ?? null
    );
};

export const resolveAdminPageTitle = (path: string): string => resolveAdminNavigation(path)?.label ?? 'Admin';
