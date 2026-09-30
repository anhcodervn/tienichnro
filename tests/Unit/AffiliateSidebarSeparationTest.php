<?php

test('affiliate commission and collaborator work sidebars do not link to each other', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $workLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateLayout.vue');
    $commissionLayout = file_get_contents($projectRoot.'/resources/js/layouts/AffiliateCommissionLayout.vue');

    expect($workLayout)
        ->not->toContain('/cong-tac-vien')
        ->not->toContain('Dashboard hoa hồng')
        ->and($commissionLayout)
        ->not->toContain('/dashboard')
        ->not->toContain('Công việc CTV');
});
