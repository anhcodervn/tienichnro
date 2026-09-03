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

test('popup notice has a dedicated general settings tab and browser dismissal controls', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $generalSettings = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/index.vue');
    $clientScript = file_get_contents($projectRoot.'/resources/js/client.js');
    $popup = file_get_contents($projectRoot.'/resources/views/components/client/home-popup.blade.php');

    expect($generalSettings)
        ->toContain("key: 'popup-notice'")
        ->toContain('adminSettingService.getPopupNotice()')
        ->toContain('adminSettingService.updatePopupNotice(popupNoticeForm.value)')
        ->toContain('v-model="popupNoticeForm.home_popup_content"')
        ->toContain('v-model="popupNoticeForm.home_popup_display_mode"')
        ->toContain('v-model="popupNoticeForm.home_popup_allow_dismiss"')
        ->toContain('v-model.number="popupNoticeForm.home_popup_dismiss_hours"')
        ->and($popup)
        ->toContain('data-home-popup')
        ->toContain('data-dismiss-enabled')
        ->toContain('data-dismiss-hours')
        ->toContain('data-display-mode')
        ->toContain('data-home-popup-dismiss')
        ->toContain('shadow-2xl outline-none')
        ->and($clientScript)
        ->toContain('const initializeHomePopup = () =>')
        ->toContain('window.localStorage.getItem(storageKey)')
        ->toContain('window.localStorage.setItem(storageKey')
        ->toContain('closePopup(true)')
        ->toContain("popup.dataset.displayMode === 'modal'")
        ->toContain('initializeHomePopup();');
});
