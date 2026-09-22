<?php

test('user list displays the non admin wallet balance summary', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/users/lists/index.vue');
    $service = file_get_contents($projectRoot.'/resources/js/services/admin-user.service.ts');

    expect($page)
        ->toContain('total_user_wallet_balance: 0')
        ->toContain('stats.total_user_wallet_balance = response.stats.total_user_wallet_balance')
        ->toContain("label: 'Tổng số dư user'")
        ->toContain('formatCurrency(stats.total_user_wallet_balance)')
        ->toContain('không bao gồm tài khoản admin')
        ->and($service)
        ->toContain('total_user_wallet_balance: number');
});
