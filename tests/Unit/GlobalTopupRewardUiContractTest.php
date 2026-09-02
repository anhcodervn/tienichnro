<?php

test('game rewards have a dedicated route menu and focused editor for every game', function (): void {
    $root = dirname(__DIR__, 2);
    $page = file_get_contents($root.'/resources/js/pages/admin/topup/global-rewards/index.vue');
    $packagePage = file_get_contents($root.'/resources/js/pages/admin/topup/global-packages/index.vue');
    $router = file_get_contents($root.'/resources/js/router/modules/admin/index.ts');
    $navigation = file_get_contents($root.'/resources/js/layouts/admin/sidebar/navigation.ts');

    expect($router)
        ->toContain("path: 'topup/global-rewards'")
        ->toContain("name: 'admin.topup.global-rewards'")
        ->and($navigation)
        ->toContain("label: 'Bảng thực nhận game'")
        ->toContain("href: '/admin/topup/global-rewards'")
        ->and($page)
        ->toContain('Bảng thực nhận game')
        ->toContain('Áp dụng cho tất cả game')
        ->toContain('v-model.number="selectedDenomination"')
        ->toContain('Bước 1')
        ->toContain('Chọn game cần cấu hình')
        ->toContain('Chọn mệnh giá thực của thẻ')
        ->toContain('Nhập đơn vị và lượng nhận được')
        ->toContain('Lưu mệnh giá')
        ->not->toContain('Lưu toàn bộ game')
        ->toContain('Chép X2 sang X3')
        ->toContain('const packages = [')
        ->toContain('denomination,')
        ->not->toContain('catalog.value.global_packages.map')
        ->not->toContain('catalog.global_packages')
        ->not->toContain('Cần có ít nhất một game dùng chế độ Global')
        ->not->toContain('global_topup_package_id')
        ->not->toContain('provider_service_code')
        ->not->toContain('Mã dịch vụ provider')
        ->not->toContain('provider_name')
        ->toContain('v-model="item.reward_x3_amount"')
        ->toContain("receive('GM', 'Gem mở')")
        ->toContain("receive('GK', 'Gem khóa')")
        ->toContain('border-2 border-slate-300 bg-slate-50')
        ->and($packagePage)
        ->not->toContain('game_settings')
        ->not->toContain('v-model="item.reward_x3_amount"');
});
