<?php

test('client support chat page is not registered on the website', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $router = file_get_contents($projectRoot.'/resources/js/router/index.ts');
    $webRoutes = file_get_contents($projectRoot.'/routes/web.php');

    expect($router)
        ->not->toContain("path: '/chat'")
        ->not->toContain("name: 'client.support.chat'")
        ->not->toContain("import('@/pages/client/support/index.vue')")
        ->and($webRoutes)
        ->not->toContain("Route::view('/chat', 'app')->name('client.support.chat')");
});

test('admin support conversation header displays the user email', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/support/index.vue');

    expect($page)
        ->toContain('{{ selectedConversation.user.email }} · ID {{ selectedConversation.user.id }}')
        ->not->toContain('@{{ selectedConversation.user.username }} · ID {{ selectedConversation.user.id }}');
});

test('admin support chat keeps the mobile conversation and composer inside the viewport', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/support/index.vue');

    expect($page)
        ->toContain('data-admin-support')
        ->toContain('h-[calc(100dvh-7rem)] min-h-0 min-w-0 overflow-hidden')
        ->not->toContain('min-h-[560px]')
        ->toContain('data-admin-support-thread')
        ->toContain('overflow-y-auto overscroll-contain')
        ->toContain('[overflow-wrap:anywhere]')
        ->toContain('data-admin-support-composer')
        ->toContain('pb-[calc(0.5rem+env(safe-area-inset-bottom))]')
        ->toContain("window.matchMedia('(min-width: 1024px)').matches")
        ->toContain('max-h-[calc(100dvh-1rem)]')
        ->toContain('sm:rounded-[16px]');
});
