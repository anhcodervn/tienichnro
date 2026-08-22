<?php

test('homepage notice editor belongs to general settings instead of content pages', function (): void {
    $generalSettings = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/settings/index.vue');
    $contentSettings = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/settings/content/index.vue');

    expect($generalSettings)
        ->toContain("key: 'homepage'")
        ->toContain('adminSettingService.getHomepage()')
        ->toContain('adminSettingService.updateHomepage(homepageForm.value)')
        ->toContain('v-model="homepageForm.home_notice_content"')
        ->toContain(':allow-images="false"')
        ->and($contentSettings)
        ->not->toContain('home_notice');
});
