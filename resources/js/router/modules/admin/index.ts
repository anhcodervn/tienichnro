export default {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    children: [
        { path: 'licenses', name: 'admin.licenses', component: () => import('@/pages/admin/licenses/index.vue') },
        {
            path: 'licenses/products',
            name: 'admin.licenses.products',
            component: () => import('@/pages/admin/licenses/index.vue'),
            props: { section: 'products' },
        },
        {
            path: 'licenses/plans',
            name: 'admin.licenses.plans',
            component: () => import('@/pages/admin/licenses/index.vue'),
            props: { section: 'plans' },
        },
        { path: 'tools', name: 'admin.tools', component: () => import('@/pages/admin/tools/index.vue') },
        { path: 'wallets', name: 'admin.wallets', component: () => import('@/pages/admin/wallets/index.vue') },
        { path: 'services', name: 'admin.services', component: () => import('@/pages/admin/services/index.vue') },
        { path: 'service-packages', name: 'admin.service-packages', component: () => import('@/pages/admin/service-packages/index.vue') },
        { path: 'nro/servers', name: 'admin.nro.servers', component: () => import('@/pages/admin/nro/servers/index.vue') },
        { path: 'nro/zalo-receivers', name: 'admin.nro.zalo-receivers', component: () => import('@/pages/admin/nro/zalo-receivers/index.vue') },
        { path: 'nro/bosses', name: 'admin.nro.bosses', component: () => import('@/pages/admin/nro/bosses/index.vue') },
        { path: 'nro/notifies', name: 'admin.nro.notifies', component: () => import('@/pages/admin/nro/notifies/index.vue') },
        {
            path: 'nro/notification-types',
            name: 'admin.nro.notification-types',
            component: () => import('@/pages/admin/nro/notification-types/index.vue'),
        },
        {
            path: '',
            redirect: { name: 'admin.seo.posts' },
        },
        {
            path: 'audit-logs',
            name: 'admin.audit-logs.index',
            component: () => import('@/pages/admin/audit-logs/index.vue'),
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
                    path: ':user_id(\\d+)',
                    name: 'admin.users.show',
                    component: () => import('@/pages/admin/users/info/index.vue'),
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
                    redirect: { name: 'admin.seo.posts' },
                },
                {
                    path: 'home',
                    name: 'admin.seo.home',
                    component: () => import('@/pages/admin/seo/home/index.vue'),
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
            path: 'settings/maintenance',
            name: 'admin.settings.maintenance',
            component: () => import('@/pages/admin/settings/maintenance/index.vue'),
        },
        {
            path: ':pathMatch(.*)*',
            name: 'admin.error.404',
            component: () => import('@/pages/errors/admin/NotFoundPage.vue'),
        },
    ],
};
