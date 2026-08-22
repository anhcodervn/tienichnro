<?php

test('topup catalog tabs are split into routes and sidebar submenus', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $router = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $routeTitles = file_get_contents($projectRoot.'/resources/js/router/index.ts');
    $navigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');

    expect($router)
        ->toContain("path: 'topup/catalog'")
        ->toContain("redirect: { name: 'admin.topup.games' }")
        ->toContain("path: 'topup/games'")
        ->toContain("name: 'admin.topup.games'")
        ->toContain("path: 'topup/servers'")
        ->toContain("name: 'admin.topup.servers'")
        ->toContain("path: 'topup/packages'")
        ->toContain("name: 'admin.topup.packages'")
        ->toContain("path: 'topup/providers'")
        ->toContain("name: 'admin.topup.providers'")
        ->toContain('@/pages/admin/topup/providers/index.vue')
        ->and($routeTitles)
        ->toContain("'admin.topup.games': 'Danh sách game'")
        ->toContain("'admin.topup.servers': 'Danh sách máy chủ game'")
        ->toContain("'admin.topup.packages': 'Danh sách gói nạp game'")
        ->toContain("'admin.topup.providers': 'Nhà cung cấp nạp game'")
        ->and($navigation)
        ->toContain("label: 'Danh mục nạp game'")
        ->toContain("href: '/admin/topup/games'")
        ->toContain("href: '/admin/topup/servers'")
        ->toContain("href: '/admin/topup/packages'")
        ->toContain("label: 'Nhà cung cấp'")
        ->toContain("href: '/admin/topup/providers'");
});

test('provider editor is separated from catalog while package provider lookup remains', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $catalog = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/catalog/index.vue');
    $providers = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/providers/index.vue');

    expect($catalog)
        ->not->toContain('Game, server, gói nạp')
        ->not->toContain('JSON cấu hình kết nối')
        ->toContain('adminTopupService.providers({ per_page: 100 })')
        ->toContain('v-for="provider in providers"')
        ->toContain('Áp dụng bộ lọc')
        ->toContain('pagination.current_page')
        ->and($providers)
        ->toContain('JSON cấu hình kết nối')
        ->toContain('adminTopupService.saveProvider')
        ->toContain('adminTopupService.deleteProvider')
        ->toContain('filters.search')
        ->toContain('pagination.current_page');
});
