export default {
    path: '/cong-tac-vien',
    component: () => import('@/layouts/AffiliateLayout.vue'),
    children: [
        {
            path: '',
            name: 'affiliate.dashboard',
            component: () => import('@/pages/affiliate/index.vue'),
        },
        {
            path: ':pathMatch(.*)*',
            redirect: { name: 'affiliate.dashboard' },
        },
    ],
};
