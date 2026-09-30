export default {
    path: '/dashboard',
    component: () => import('@/layouts/AffiliateLayout.vue'),
    children: [
        {
            path: '',
            name: 'collaborator.dashboard',
            component: () => import('@/pages/affiliate/collaborator-dashboard/index.vue'),
        },
        {
            path: 'don-dich-vu',
            name: 'collaborator.orders',
            component: () => import('@/pages/affiliate/game-service-orders/index.vue'),
        },
        {
            path: 'chat-don',
            name: 'collaborator.chats',
            component: () => import('@/pages/affiliate/game-service-chats/index.vue'),
        },
        {
            path: 'thong-bao',
            name: 'collaborator.notifications',
            component: () => import('@/pages/affiliate/collaborator-notifications/index.vue'),
        },
        {
            path: 'doanh-thu',
            name: 'collaborator.revenue',
            component: () => import('@/pages/affiliate/revenue/index.vue'),
        },
        {
            path: 'rut-tien',
            name: 'collaborator.withdrawal',
            component: () => import('@/pages/affiliate/withdrawal/index.vue'),
        },
        {
            path: ':pathMatch(.*)*',
            redirect: { name: 'collaborator.dashboard' },
        },
    ],
};
