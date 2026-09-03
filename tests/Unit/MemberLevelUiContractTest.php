<?php

test('member level navigation and storefront widgets are retired', function (): void {
    $router = file_get_contents(dirname(__DIR__, 2).'/resources/js/router/modules/admin/index.ts');
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/admin/sidebar/navigation.ts');
    $topupForm = file_get_contents(dirname(__DIR__, 2).'/resources/views/client/components/topup-form.blade.php');
    $profile = file_get_contents(dirname(__DIR__, 2).'/resources/views/client/account/profile.blade.php');

    expect($router)->not->toContain('/admin/member-levels')
        ->and($sidebar)->not->toContain('member-levels')
        ->and($topupForm)->not->toContain('member_level')
        ->and($profile)->not->toContain('memberLevel');
});

test('user detail owns the per member pricing interface', function (): void {
    $userDetail = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/users/info/index.vue');
    $userService = file_get_contents(dirname(__DIR__, 2).'/resources/js/services/admin-user.service.ts');

    expect($userDetail)->toContain("key: 'pricing'", 'Giá riêng theo từng thành viên', 'website hoặc qua API')
        ->and($userDetail)->toContain('border-2 border-slate-300', 'member_price', 'minimum_profit')
        ->and($userDetail)->toContain('globalPackageRows', "pricing_source === 'global'", 'saveGlobalPrice')
        ->and($userDetail)->toContain('selectedPricingScope', 'gamePricingScopes', 'selectedGamePriceRows', 'Chọn phạm vi set giá')
        ->and($userDetail)->toContain('Giá riêng từng gói Global', 'Chỉnh riêng từng dòng giống bảng giá gói thường')
        ->and($userService)->toContain('/prices/${packageId}', 'updatePrice', 'deletePrice')
        ->and($userService)->toContain('/global-prices/${globalPackageId}', 'updateGlobalPrice', 'deleteGlobalPrice');
});
