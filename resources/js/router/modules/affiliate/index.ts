export default {
    path: '/cong-tac-vien',
    component: () => import('@/layouts/AffiliateLayout.vue'),
    children: [
        {
            path: '',
            name: 'affiliate.home',
            component: () => import('@/pages/affiliate/home.vue'),
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
            path: ':pathMatch(.*)*',
            redirect: { name: 'affiliate.home' },
        },
    ],
};
