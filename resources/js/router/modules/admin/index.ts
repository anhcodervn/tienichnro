export default {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    children: [
        {
            path: 'sites',
            name: 'admin.sites.index',
            component: () => import('@/pages/admin/sites/index.vue'),
        },
        {
            path: 'site-prices',
            name: 'admin.site-prices.index',
            component: () => import('@/pages/admin/site-prices/index.vue'),
        },
        {
            path: '',
            name: 'admin.dashboard',
            component: () => import('@/pages/admin/home/index.vue'),
        },
        {
            path: 'reports',
            name: 'admin.reports.index',
            component: () => import('@/pages/admin/reports/index.vue'),
        },
        {
            path: 'audit-logs',
            name: 'admin.audit-logs.index',
            component: () => import('@/pages/admin/audit-logs/index.vue'),
        },
        {
            path: 'affiliate',
            name: 'admin.affiliate.index',
            component: () => import('@/pages/admin/affiliate/index.vue'),
        },
        {
            path: 'affiliate/announcements',
            name: 'admin.affiliate.announcements',
            component: () => import('@/pages/admin/affiliate/announcements.vue'),
        },
        {
            path: 'support',
            name: 'admin.support.index',
            component: () => import('@/pages/admin/support/index.vue'),
        },
        {
            path: 'topup/catalog',
            name: 'admin.topup.catalog',
            redirect: { name: 'admin.topup.games' },
        },
        {
            path: 'topup/games',
            name: 'admin.topup.games',
            component: () => import('@/pages/admin/topup/catalog/index.vue'),
            props: { catalogType: 'games' },
        },
        {
            path: 'topup/servers',
            name: 'admin.topup.servers',
            component: () => import('@/pages/admin/topup/catalog/index.vue'),
            props: { catalogType: 'servers' },
        },
        {
            path: 'topup/packages',
            name: 'admin.topup.packages',
            component: () => import('@/pages/admin/topup/catalog/index.vue'),
            props: { catalogType: 'packages' },
        },
        {
            path: 'topup/global-packages',
            name: 'admin.topup.global-packages',
            component: () => import('@/pages/admin/topup/global-packages/index.vue'),
        },
        {
            path: 'topup/global-rewards',
            name: 'admin.topup.global-rewards',
            component: () => import('@/pages/admin/topup/global-rewards/index.vue'),
        },
        {
            path: 'topup/providers',
            name: 'admin.topup.providers',
            component: () => import('@/pages/admin/topup/providers/index.vue'),
        },
        {
            path: 'topup/provider-prices',
            name: 'admin.topup.provider-prices',
            component: () => import('@/pages/admin/topup/provider-prices/index.vue'),
        },
        {
            path: 'topup/orders',
            name: 'admin.topup.orders',
            component: () => import('@/pages/admin/topup/orders/index.vue'),
        },
        {
            path: 'users',
            children: [
                {
                    path: '',
                    name: 'admin.users.index',
                    component: () => import('@/pages/admin/users/lists/index.vue'),
                },
                {
                    path: 'discounts',
                    name: 'admin.users.discounts',
                    component: () => import('@/pages/admin/users/discounts/index.vue'),
                },
                {
                    path: ':user_id(\\d+)',
                    name: 'admin.users.show',
                    component: () => import('@/pages/admin/users/info/index.vue'),
                },
                {
                    path: 'wallet-transactions',
                    name: 'admin.users.wallet-transaction',
                    component: () => import('@/pages/admin/users/wallet-transactions/index.vue'),
                },
                {
                    path: 'wallet-transactions/:user_id(\\d+)',
                    name: 'admin.users.wallet-transaction.show',
                    component: () => import('@/pages/admin/users/wallet-transactions/index.vue'),
                },
            ],
        },
        {
            path: 'notifications',
            children: [
                {
                    path: '',
                    name: 'admin.notifications.index',
                    component: () => import('@/pages/admin/notifications/list/index.vue'),
                },
                {
                    path: 'create',
                    name: 'admin.notifications.create',
                    component: () => import('@/pages/admin/notifications/form/index.vue'),
                },
                {
                    path: ':notification_id(\\d+)/edit',
                    name: 'admin.notifications.edit',
                    component: () => import('@/pages/admin/notifications/form/index.vue'),
                },
                {
                    path: 'history',
                    name: 'admin.notifications.history',
                    component: () => import('@/pages/admin/notifications/history/index.vue'),
                },
            ],
        },
        {
            path: 'mail',
            name: 'admin.mail.index',
            component: () => import('@/pages/admin/mail/index.vue'),
        },
        {
            path: 'queues',
            name: 'admin.queues.index',
            component: () => import('@/pages/admin/queues/index.vue'),
        },
        {
            path: 'feedbacks',
            name: 'admin.feedbacks.index',
            component: () => import('@/pages/admin/feedbacks/index.vue'),
        },
        {
            path: 'seo',
            children: [
                {
                    path: '',
                    name: 'admin.seo.dashboard',
                    component: () => import('@/pages/admin/seo/dashboard/index.vue'),
                },
                {
                    path: 'categories',
                    name: 'admin.seo.categories',
                    component: () => import('@/pages/admin/seo/categories/index.vue'),
                },
                {
                    path: 'posts',
                    name: 'admin.seo.posts',
                    component: () => import('@/pages/admin/seo/posts/index.vue'),
                },
                {
                    path: 'posts/create',
                    name: 'admin.seo.posts.create',
                    component: () => import('@/pages/admin/seo/posts/create/index.vue'),
                },
                {
                    path: 'posts/:seo_post_id(\\d+)/edit',
                    name: 'admin.seo.posts.edit',
                    component: () => import('@/pages/admin/seo/posts/create/index.vue'),
                },
                {
                    path: 'sitemaps',
                    name: 'admin.seo.sitemaps',
                    component: () => import('@/pages/admin/seo/sitemaps/index.vue'),
                },
            ],
        },
        {
            path: 'recharge',
            redirect: { name: 'admin.recharge.config' },
        },
        {
            path: 'recharge/config',
            name: 'admin.recharge.config',
            component: () => import('@/pages/admin/settings/recharge/index.vue'),
        },
        {
            path: 'recharge/history',
            name: 'admin.recharge.history',
            component: () => import('@/pages/admin/recharge/history/index.vue'),
        },
        {
            path: 'setting',
            redirect: { name: 'admin.settings.general' },
        },
        {
            path: 'settings/general',
            name: 'admin.settings.general',
            component: () => import('@/pages/admin/settings/index.vue'),
        },
        {
            path: 'settings/content',
            name: 'admin.settings.content',
            component: () => import('@/pages/admin/settings/content/index.vue'),
        },
        {
            path: 'settings/bio',
            name: 'admin.settings.bio',
            component: () => import('@/pages/admin/settings/bio/index.vue'),
        },
        {
            path: 'settings/recharge',
            redirect: { name: 'admin.recharge.config' },
        },
        {
            path: ':pathMatch(.*)*',
            name: 'admin.error.404',
            component: () => import('@/pages/errors/admin/NotFoundPage.vue'),
        },
    ],
};
