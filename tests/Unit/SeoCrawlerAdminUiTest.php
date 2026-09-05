<?php

test('admin seo settings expose robots and ads editors', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/index.vue');
    $types = file_get_contents($projectRoot.'/resources/js/types/setting.type.ts');
    $sitemapPage = file_get_contents($projectRoot.'/resources/js/pages/admin/seo/sitemaps/index.vue');
    $seoService = file_get_contents($projectRoot.'/app/Features/Admin/Seo/Services/SeoService.php');

    expect($page)
        ->toContain('v-model="seoForm.robots_txt"')
        ->toContain('v-model="seoForm.ads_txt"')
        ->toContain('href="/robots.txt"')
        ->toContain('href="/ads.txt"')
        ->toContain('Sitemap phải dùng URL đầy đủ')
        ->and($types)
        ->toContain('robots_txt: string;')
        ->toContain('ads_txt: string;')
        ->and($sitemapPage)
        ->toContain('Sitemap chỉ chứa URL public, index/follow và trùng với canonical.')
        ->and($seoService)
        ->toContain("'sitemap_files' => 1")
        ->not->toContain('/sitemap-posts.xml')
        ->not->toContain('/sitemap-categories.xml')
        ->not->toContain('/sitemap-pages.xml');
});
