<?php

test('client support chat page exposes realtime messaging controls', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/views/client/support/index.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client-support.js');

    expect($page)
        ->toContain('data-support-chat')
        ->toContain("route('client.support.index')")
        ->toContain("route('client.support.messages.store')")
        ->toContain("route('client.support.read')")
        ->toContain('data-channel="users.')
        ->toContain('data-support-message-area')
        ->toContain('data-support-load-older')
        ->toContain('data-support-form')
        ->toContain('data-support-send-label')
        ->toContain('Tối đa 1 tin/10 giây · 6 tin/phút')
        ->toContain('maxlength="5000"')
        ->and($script)
        ->toContain('echo.private(channelName)')
        ->toContain("channel.listen('.support.message.created'")
        ->toContain("channel.listen('.support.messages.read'")
        ->toContain("channel.listen('.support.conversation.updated'")
        ->toContain('messages.set(Number(message.id)')
        ->toContain("'X-CSRF-TOKEN': csrfToken")
        ->toContain("credentials: 'same-origin'")
        ->toContain("response.headers.get('Retry-After') || payload.data?.retry_after")
        ->toContain('startCooldown(10)')
        ->toContain('`Gửi sau ${remaining}s`')
        ->toContain('if (error.status === 429) startCooldown(error.retryAfter || 10)')
        ->toContain('loadThread({ cursor: nextCursor, older: true })')
        ->toContain('echo.leave(chat.dataset.channel)')
        ->not->toContain('innerHTML');
});

test('admin support conversation header displays the user email', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/support/index.vue');

    expect($page)
        ->toContain('{{ selectedConversation.user.email }} · ID {{ selectedConversation.user.id }}')
        ->not->toContain('@{{ selectedConversation.user.username }} · ID {{ selectedConversation.user.id }}');
});
