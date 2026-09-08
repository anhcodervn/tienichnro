<?php

test('shared branding is connected to blade and admin vue surfaces', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $providers = file_get_contents($projectRoot.'/bootstrap/providers.php');
    $viewProvider = file_get_contents($projectRoot.'/app/Providers/SharedViewServiceProvider.php');
    $clientLayout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $maintenance = file_get_contents($projectRoot.'/resources/views/pages/maintenance/index.blade.php');
    $seoPost = file_get_contents($projectRoot.'/resources/views/pages/seo/show.blade.php');
    $settingsPage = file_get_contents($projectRoot.'/resources/js/pages/admin/settings/index.vue');
    $adminSidebar = file_get_contents($projectRoot.'/resources/js/layouts/admin/sidebar/index.vue');

    expect($providers)
        ->toContain('use App\Providers\SharedViewServiceProvider;')
        ->toContain('SharedViewServiceProvider::class')
        ->and($viewProvider)
        ->toContain("ViewFacade::composer('client.layouts.app'")
        ->toContain("'light_logo' => ''")
        ->toContain("'dark_logo' => ''")
        ->toContain("'favicon' => ''")
        ->toContain("'og_image' => ''")
        ->and($clientLayout)
        ->toContain("\$settings['dark_logo']")
        ->toContain('<meta property="og:image" content="{{ $shareImage }}">')
        ->toContain('<meta name="twitter:image" content="{{ $shareImage }}">')
        ->toContain('<link rel="apple-touch-icon" href="{{ $favicon }}">')
        ->toContain('h-auto w-32 shrink-0 object-contain object-left sm:w-40')
        ->and($maintenance)
        ->toContain("\$settings['light_logo'] ?: (\$settings['dark_logo'] ?: null)")
        ->and($seoPost)
        ->toContain("@section('image'){{ \$pageMetaImage }}@endsection")
        ->toContain("@section('og_type', \$pageOgType)")
        ->and($settingsPage)
        ->toContain('Logo nền tối')
        ->toContain('Logo nền sáng')
        ->toContain('Ảnh chia sẻ mặc định')
        ->toContain('void refreshSharedSettings(true).catch(() => {})')
        ->and($adminSidebar)
        ->toContain('const sidebarLogo = computed(() => settings.value.dark_logo || settings.value.light_logo)')
        ->toContain('v-if="sidebarLogo"')
        ->toContain('h-auto w-24 shrink-0 object-contain object-left')
        ->toContain("{{ settings.site_name || 'Nạp Carot' }}");
});
