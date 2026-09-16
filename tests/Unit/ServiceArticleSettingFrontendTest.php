<?php

test('settings expose repeatable service and footer editors with guarded client links', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $settingsPage = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/index.vue');
    $settingTypes = file_get_contents($projectRoot.'/resources/js/types/setting.type.ts');
    $settingService = file_get_contents($projectRoot.'/resources/js/services/admin-setting.service.ts');
    $clientLayout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');

    expect($settingsPage)
        ->toContain("key: 'service-articles'")
        ->toContain("label: 'Bài viết dịch vụ'")
        ->toContain('v-model="serviceArticlesForm.game_service_enabled"')
        ->toContain('v-for="(item, index) in serviceArticlesForm.game_service_items"')
        ->toContain('v-model="item.label"')
        ->toContain('v-model="item.url"')
        ->toContain('@click="addServiceArticleItem"')
        ->toContain('@click="removeServiceArticleItem(index)"')
        ->toContain('v-for="(item, index) in generalForm.footer_game_links"')
        ->toContain('@click="addFooterGameLink"')
        ->toContain('@click="removeFooterGameLink(index)"')
        ->toContain('@click="saveServiceArticles"')
        ->and($settingTypes)
        ->toContain('export interface GameServiceMenuItem')
        ->toContain('game_service_items: GameServiceMenuItem[]')
        ->toContain('footer_game_links: GameServiceMenuItem[]')
        ->and($settingService)
        ->toContain("getTab<ServiceArticlesSettingType>('service-articles')")
        ->toContain("updateTab<ServiceArticlesSettingType>('service-articles', payload)")
        ->and($clientLayout)
        ->toContain('$showGameServiceMenu')
        ->toContain('data-game-service-menu')
        ->toContain('data-game-service-link')
        ->toContain('data-footer-game-links')
        ->toContain('data-footer-game-link')
        ->toContain("'lg:grid-cols-5' => \$footerGameLinks !== []")
        ->toContain('@foreach ($gameServiceItems as $gameServiceItem)');
});
