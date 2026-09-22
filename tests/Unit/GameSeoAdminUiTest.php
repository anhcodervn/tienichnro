<?php

test('game seo management page is connected to seo navigation and api', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/seo/games/index.vue');
    $routes = file_get_contents($projectRoot.'/resources/js/router/modules/admin/index.ts');
    $navigation = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/navigation.ts');
    $service = file_get_contents($projectRoot.'/resources/js/services/admin-seo.service.ts');
    $gameCatalog = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/catalog/index.vue');

    expect($routes)
        ->toContain("name: 'admin.seo.games'")
        ->toContain('@/pages/admin/seo/games/index.vue')
        ->and($navigation)
        ->toContain("label: 'SEO từng game'")
        ->toContain("href: '/admin/seo/games'")
        ->and($service)
        ->toContain("api.get('/api/admin-api/seo/games')")
        ->toContain('api.patch(`/api/admin-api/seo/games/${id}`')
        ->and($page)
        ->toContain('v-model="form.meta_title"')
        ->toContain('v-model="form.meta_description"')
        ->toContain('v-model="form.meta_keywords"')
        ->toContain('<Editor ref="contentEditor" v-model="form.content"')
        ->toContain('<UploadImage')
        ->toContain('v-model="form.canonical_url"')
        ->toContain('v-model="form.robots"')
        ->and($gameCatalog)
        ->not->toContain('SEO trang nạp game')
        ->not->toContain('form.seo_title')
        ->not->toContain('form.seo_description')
        ->not->toContain('form.content');
});
