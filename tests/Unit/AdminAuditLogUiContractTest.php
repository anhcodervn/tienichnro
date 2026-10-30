<?php

test('admin navigation and router no longer expose audit logs', function (): void {
    $router = file_get_contents(resource_path('js/router/modules/admin/index.ts'));
    $navigation = file_get_contents(resource_path('js/layouts/admin/sidebar/navigation.ts'));

    expect($router)->not->toContain('audit-logs')
        ->and($navigation)->not->toContain('audit-logs')
        ->and(file_exists(resource_path('js/pages/admin/audit-logs/index.vue')))->toBeFalse()
        ->and(file_exists(resource_path('js/services/admin-audit-log.service.ts')))->toBeFalse();
});
