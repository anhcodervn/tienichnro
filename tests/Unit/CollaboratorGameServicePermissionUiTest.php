<?php

test('admin user detail exposes collaborator service permission datatable', function (): void {
    $userDetail = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/users/info/index.vue');
    $userService = file_get_contents(dirname(__DIR__, 2).'/resources/js/services/admin-user.service.ts');

    expect($userDetail)
        ->toContain("key: 'services'", 'Dịch vụ cho phép', '<DataTable', 'selectedGameServiceIds', 'saveGameServicePermissions')
        ->and($userDetail)->toContain("detail.value?.role === 'ctv'", 'Lưu dịch vụ cho phép')
        ->and($userDetail)->toContain('secondaryPasswordForm', 'submitGameServiceSecondaryPassword', 'Mật khẩu C2 riêng của CTV')
        ->and($userService)->toContain('/game-services', 'game_service_ids', 'syncGameServices', 'updateGameServiceSecondaryPassword');
});
