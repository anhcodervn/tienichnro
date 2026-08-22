<?php

test('active admin settings page mounts the custom code section', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $settingsPage = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/index.vue');
    $customCode = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/CustomCodeSettings.vue');

    expect($settingsPage)
        ->toContain("import CustomCodeSettings from '@/pages/admin/settings/CustomCodeSettings.vue'")
        ->toContain("key: 'custom-code'")
        ->toContain('<CustomCodeSettings v-show="activeTab === \'custom-code\'" />')
        ->not->toContain('seoForm.custom_script')
        ->and($customCode)
        ->toContain('v-model="form.custom_css_enabled"')
        ->toContain('v-model="form.custom_js_enabled"')
        ->toContain(':maxlength="MAX_CODE_LENGTH"')
        ->toContain('cssLength.toLocaleString')
        ->toContain('jsLength.toLocaleString')
        ->toContain('confirmJavaScriptChange')
        ->toContain('data?.data?.errors')
        ->not->toContain('v-html')
        ->not->toContain('eval(');
});

test('admin layout no longer renders the legacy custom script', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/app.blade.php');

    expect($layout)
        ->not->toContain('custom_script')
        ->not->toContain('data-site-custom-js');
});
