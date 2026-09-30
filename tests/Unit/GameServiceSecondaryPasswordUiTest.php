<?php

test('secondary password UI gates collaborator dashboard and protects admin payload access', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $affiliateLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateLayout.vue');
    $adminOrders = file_get_contents($projectRoot.'/resources/js/pages/admin/game-services/orders/index.vue');
    $collaboratorOrders = file_get_contents($projectRoot.'/resources/js/pages/affiliate/game-service-orders/index.vue');
    $axios = file_get_contents($projectRoot.'/resources/js/config/axios.ts');

    expect($affiliateLayout)
        ->toContain('<SecondaryPasswordDialog')
        ->toContain('v-else-if="secondaryUnlocked"')
        ->toContain('blocking')
        ->toContain('checkSecondaryPassword()')
        ->toContain(":personal=\"userStore.user?.role === 'ctv'\"")
        ->and($adminOrders)
        ->toContain('selectedOrder.payload_locked')
        ->toContain('Mở khóa và xem payload')
        ->toContain('adminGameServiceService.orderPayload(selectedCode)')
        ->and($collaboratorOrders)
        ->toContain('clientAffiliateService.gameServiceOrderPayload(order.code)')
        ->and($axios)
        ->toContain('X-Game-Service-Secondary-Token')
        ->toContain('SECONDARY_PASSWORD_REQUIRED');
});
