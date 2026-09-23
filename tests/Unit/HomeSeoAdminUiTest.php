<?php

test('admin home seo page is wired to navigation api and safe editor upload flow', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/seo/home/index.vue');
    $routes = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $navigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $service = file_get_contents($projectRoot.'/resources/js/services/admin-seo.service.ts');

    expect($routes)
        ->toContain("name: 'admin.seo.home'")
        ->toContain('@/pages/admin/seo/home/index.vue')
        ->and($navigation)
        ->toContain("label: 'SEO trang chủ'")
        ->toContain("href: '/admin/seo/home'")
        ->and($service)
        ->toContain("api.get('/api/admin-api/seo/home')")
        ->toContain("api.patch('/api/admin-api/seo/home'")
        ->and($page)
        ->toContain('contentEditor.value?.flush()')
        ->toContain('uploadEditorImages(form.content)')
        ->toContain('v-model="form.meta_keywords"')
        ->toContain('meta_keywords: form.meta_keywords.trim()')
        ->toContain('v-model="form.is_published"');
});
