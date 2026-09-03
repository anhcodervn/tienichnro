<?php

test('recharge settings and client deposit expose bonus tier management and preview', function (): void {
    $adminPage = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/settings/recharge/index.vue');
    $clientPage = file_get_contents(dirname(__DIR__, 2).'/resources/views/client/wallet/deposit.blade.php');
    $clientScript = file_get_contents(dirname(__DIR__, 2).'/resources/js/client.js');

    expect($adminPage)
        ->toContain('Mốc khuyến mãi số dư')
        ->toContain('openCreateBonusModal')
        ->toContain('bonusForm.minimum_amount')
        ->toContain('bonusForm.bonus_percent')
        ->toContain('border-2 border-slate-300')
        ->toContain('v-if="!isMainSite"')
        ->toContain('Callback bắt buộc cho ApiBankVn')
        ->toContain('Sao chép callback')
        ->toContain('Site con chỉ được tạo cấu hình nạp tiền bằng ApiBankVn')
        ->toContain('v-if="isMainSite"')
        ->and($clientPage)
        ->toContain('Khuyến mãi cộng thêm số dư')
        ->toContain('data-deposit-bonus-tiers')
        ->toContain('data-deposit-summary-bonus')
        ->and($clientScript)
        ->toContain('bonusTier')
        ->toContain('bonus_basis_points')
        ->toContain('amount + bonusAmount');
});
