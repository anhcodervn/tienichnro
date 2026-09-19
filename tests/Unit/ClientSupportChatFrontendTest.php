<?php

test('client support chat page exposes realtime messaging controls', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/client/support/index.vue');
    $router = file_get_contents($projectRoot.'/resources/js/router/index.ts');
    $webRoutes = file_get_contents($projectRoot.'/routes/web.php');
    $vite = file_get_contents($projectRoot.'/vite.config.ts');

    expect($page)
        ->toContain('data-support-chat-app')
        ->toContain('class="flex h-[100dvh]')
        ->toContain('data-support-back-home')
        ->toContain('href="/"')
        ->toContain('aria-label="Trở lại trang chủ"')
        ->toContain('data-support-message-area')
        ->toContain('data-support-load-older')
        ->toContain('data-support-form')
        ->toContain('data-support-send')
        ->toContain('pb-[env(safe-area-inset-bottom)]')
        ->toContain('maxlength="5000"')
        ->toContain("supportStore.start('client', Number(user.id))")
        ->toContain("window.addEventListener('support:message-created'")
        ->toContain("window.addEventListener('support:messages-read'")
        ->toContain('supportService.clientThread(cursor)')
        ->toContain('supportService.clientSend(content)')
        ->toContain('supportService.clientMarkRead()')
        ->toContain('startCooldown(Number(error.response.headers')
        ->toContain("if (event.key !== 'Enter' || event.shiftKey) return")
        ->toContain('supportStore.stop()')
        ->not->toContain('v-html')
        ->and($router)
        ->toContain("path: '/chat'")
        ->toContain("name: 'client.support.chat'")
        ->toContain("import('@/pages/client/support/index.vue')")
        ->and($webRoutes)
        ->toContain("Route::view('/chat', 'app')->name('client.support.chat')")
        ->and($vite)
        ->not->toContain("'resources/js/client-support.js'");
});

test('admin support conversation header displays the user email', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/support/index.vue');

    expect($page)
        ->toContain('{{ selectedConversation.user.email }} · ID {{ selectedConversation.user.id }}')
        ->not->toContain('@{{ selectedConversation.user.username }} · ID {{ selectedConversation.user.id }}');
});
