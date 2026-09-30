<?php

test('secondary password UI unlocks sensitive data on demand and keeps the grant in the current tab', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $affiliateLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateLayout.vue');
    $adminOrders = file_get_contents($projectRoot.'/resources/js/pages/admin/game-services/orders/index.vue');
    $collaboratorOrders = file_get_contents($projectRoot.'/resources/js/pages/affiliate/game-service-orders/index.vue');
    $axios = file_get_contents($projectRoot.'/resources/js/config/axios.ts');
    $secondaryAuthStorage = file_get_contents($projectRoot.'/resources/js/utils/game-service-secondary-auth.ts');

    expect($affiliateLayout)
        ->toContain('<SecondaryPasswordDialog')
        ->toContain('<RouterView />')
        ->toContain(':open="secondaryDialogOpen"')
        ->toContain('checkSecondaryPassword()')
        ->toContain(":personal=\"userStore.user?.role === 'ctv'\"")
        ->not->toContain('v-else-if="secondaryUnlocked"')
        ->not->toContain(' blocking')
        ->and($adminOrders)
        ->toContain('selectedOrder.payload_locked')
        ->toContain('Mở khóa và xem payload')
        ->toContain('adminGameServiceService.orderPayload(selectedCode)')
        ->and($collaboratorOrders)
        ->toContain('gameServiceSecondaryAuthService.status()')
        ->toContain('clientAffiliateService.gameServiceOrderPayload(workOrder.value.code)')
        ->toContain('Xem tài khoản')
        ->toContain('<EyeOff')
        ->and($axios)
        ->toContain('X-Game-Service-Secondary-Token')
        ->toContain('SECONDARY_PASSWORD_REQUIRED')
        ->and($secondaryAuthStorage)
        ->toContain('window.sessionStorage')
        ->not->toContain('window.localStorage')
        ->not->toContain('expires_at')
        ->and($affiliateLayout)
        ->not->toContain('secondaryExpiryTimer')
        ->not->toContain('scheduleSecondaryExpiry');
});
