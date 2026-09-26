<?php

test('admin maintenance page is wired into navigation router and setting service', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $navigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $adminRouter = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $settingService = file_get_contents($projectRoot.'/resources/js/services/admin-setting.service.ts');
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/maintenance/index.vue');

    expect($navigation)
        ->toContain('/admin/settings/maintenance')
        ->and($adminRouter)->toContain("name: 'admin.settings.maintenance'")
        ->and($settingService)->toContain("getTab<MaintenanceSettingType>('maintenance')")
        ->and($settingService)->toContain("updateTab<MaintenanceSettingType>('maintenance', payload)")
        ->and($page)->toContain('Bảo trì website')
        ->and($page)->toContain('Bảo trì cổng nạp game');
});
