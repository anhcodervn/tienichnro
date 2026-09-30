export default {
    path: '/cong-tac-vien',
    component: () => import('@/layouts/AffiliateCommissionLayout.vue'),
    children: [
        {
            path: '',
            name: 'affiliate.dashboard',
            component: () => import('@/pages/affiliate/index.vue'),
        },
        {
            path: 'thong-bao',
            name: 'affiliate.notifications',
            component: () => import('@/pages/affiliate/home.vue'),
        },
        {
            path: 'tong-quan',
            redirect: { name: 'affiliate.dashboard' },
        },
        {
            path: 'bang-gia-chiet-khau',
            name: 'affiliate.rates',
            component: () => import('@/pages/affiliate/rates.vue'),
        },
        {
            path: ':pathMatch(.*)*',
            redirect: { name: 'affiliate.dashboard' },
        },
    ],
};
