export default {
    path: '/cong-tac-vien',
    component: () => import('@/layouts/AffiliateLayout.vue'),
    children: [
        {
            path: '',
            name: 'affiliate.collaborator.dashboard',
            component: () => import('@/pages/affiliate/collaborator-dashboard/index.vue'),
        },
        {
            path: 'thong-bao',
            name: 'affiliate.notifications',
            component: () => import('@/pages/affiliate/home.vue'),
        },
        {
            path: 'doanh-thu',
            name: 'affiliate.revenue',
            component: () => import('@/pages/affiliate/revenue/index.vue'),
        },
        {
            path: 'rut-tien',
            name: 'affiliate.withdrawal',
            component: () => import('@/pages/affiliate/withdrawal/index.vue'),
        },
        {
            path: 'tong-quan',
            name: 'affiliate.dashboard',
            component: () => import('@/pages/affiliate/index.vue'),
        },
        {
            path: 'bang-gia-chiet-khau',
            name: 'affiliate.rates',
            component: () => import('@/pages/affiliate/rates.vue'),
        },
        {
            path: 'don-dich-vu',
            name: 'affiliate.game-service-orders',
            component: () => import('@/pages/affiliate/game-service-orders/index.vue'),
        },
        {
            path: ':pathMatch(.*)*',
            redirect: { name: 'affiliate.collaborator.dashboard' },
        },
    ],
};
