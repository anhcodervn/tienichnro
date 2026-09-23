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
        ->toContain('adapter tạo payload provider')
        ->toContain('<code>username</code>')
        ->toContain('<code>account</code>')
        ->not->toContain('accnrovn_account_field')
        ->toContain('Áp dụng bộ lọc')
        ->toContain('pagination.current_page')
        ->and($providers)
        ->toContain('v-model="form.payload_field_mapping_text"')
        ->toContain('payload_field_mapping: parsedPayloadFieldMapping')
        ->toContain('provider.payload_field_mapping_editor || provider.payload_field_mapping')
        ->toContain('"game_account": "username"')
        ->toContain('"character_name": "charname"')
        ->toContain('JSON cấu hình kết nối')
        ->toContain('adminTopupService.saveProvider')
        ->toContain('adminTopupService.deleteProvider')
        ->toContain('adminTopupService.providerServices')
        ->toContain('<Modal v-model="servicesModalOpen"')
        ->toContain('Xem services')
        ->toContain('Lấy services')
        ->toContain('navigator.clipboard.writeText(servicesResponseText.value)')
        ->toContain("slug.trim().toLowerCase() === 'accnrovn'")
        ->toContain('https://accnro.vn/api/v1/partner/recharge')
        ->toContain('Cần nhập gì?')
        ->toContain('https://api.provider.example/rechargews')
        ->toContain("the9p: 'https://the9p.com/api/rechargews'")
        ->toContain("napgame1s: 'https://napgame1s.net/api/rechargews'")
        ->toContain("key: 'partner_key', required: true")
        ->toContain("key: 'secret_key', required: true")
        ->toContain("field.required ? 'Bắt buộc' : 'Tùy chọn'")
        ->toContain('form.minimum_profit_percent')
        ->toContain('Lợi nhuận tối thiểu tự động')
        ->toContain('(giá bán − giá provider) / giá bán')
        ->toContain('filters.search')
        ->toContain('pagination.current_page');
});

test('topup tables keep actions visible and only split editors on wide screens', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $catalog = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/catalog/index.vue');
    $providers = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/providers/index.vue');
    $orders = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/orders/index.vue');

    expect($catalog)
        ->toContain('min-[1800px]:grid-cols-[minmax(0,1fr)_390px]')
        ->toContain('sticky right-0 z-10 whitespace-nowrap bg-slate-50')
        ->toContain('sticky right-0 z-10 whitespace-nowrap bg-white')
        ->and($providers)
        ->toContain('min-[1800px]:grid-cols-[minmax(0,1fr)_400px]')
        ->toContain('sticky right-0 z-10 whitespace-nowrap bg-slate-50')
        ->toContain('sticky right-0 z-10 whitespace-nowrap bg-white')
        ->and($orders)
        ->toContain('sticky right-0 z-10 whitespace-nowrap bg-slate-50')
        ->toContain('sticky right-0 z-10 bg-white');
});
