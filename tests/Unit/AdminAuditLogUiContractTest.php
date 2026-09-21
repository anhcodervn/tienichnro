<?php

test('admin audit log management page is registered with filters and append only details', function (): void {
    $page = file_get_contents(resource_path('js/pages/admin/audit-logs/index.vue'));
    $service = file_get_contents(resource_path('js/services/admin-audit-log.service.ts'));
    $router = file_get_contents(resource_path('js/router/modules/admin/index.ts'));
    $navigation = file_get_contents(resource_path('js/layouts/admin/sidebar/navigation.ts'));

    expect($page)
        ->toContain('Nhật ký quản trị')
        ->toContain('Dữ liệu nhạy cảm được tự động che')
        ->toContain('filters.admin_id')
        ->toContain('filters.action')
        ->toContain('filters.method')
        ->toContain('filters.status_code')
        ->toContain('selectedLog')
        ->not->toContain('deleteLog')
        ->and($service)->toContain('/api/admin-api/audit-logs')
        ->and($router)->toContain("path: 'audit-logs'")
        ->and($navigation)->toContain("href: '/admin/audit-logs'");
});
