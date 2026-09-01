<?php

test('admin dashboard highlights operational values with icons and status badges', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/home/index.vue');

    expect($source)
        ->toContain('const metricCards = computed')
        ->toContain('const needsAttention = computed')
        ->toContain('mục cần theo dõi')
        ->toContain(':class="paymentStatus(order.payment_status).class"')
        ->toContain(':class="orderStatus(order.order_status).class"')
        ->toContain('<component :is="metric.icon"');
});

test('operational admin pages consistently emphasize tracked counts', function (string $relativePath, array $markers): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)->toContain(...$markers);
})->with([
    'topup orders' => [
        'resources/js/pages/admin/topup/orders/index.vue',
        ['const summaries = computed', 'Thống kê số lượng thẻ hôm nay', 'Tổng thẻ hôm nay', 'statistics.pending_payment', 'ring-rose-600/20'],
    ],
    'queues' => [
        'resources/js/pages/admin/queues/index.vue',
        ['CircleAlert', 'RefreshCw', 'bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700'],
    ],
    'notifications' => [
        'resources/js/pages/admin/notifications/list/index.vue',
        ['BellRing', 'RadioTower', 'Eye', 'ring-sky-600/20'],
    ],
    'recharge history' => [
        'resources/js/pages/admin/recharge/history/index.vue',
        ['CircleDollarSign', 'ShieldAlert', 'ScanSearch', 'text-rose-600'],
    ],
]);
