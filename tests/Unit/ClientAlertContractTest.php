<?php

test('client errors use sweetalert while the homepage announcement remains admin configured', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $flash = file_get_contents($projectRoot.'/resources/views/client/partials/flash.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $controller = file_get_contents($projectRoot.'/app/Features/Client/Topup/Controllers/HomeController.php');

    expect($flash)
        ->toContain('hidden data-client-alert')
        ->toContain('data-alert-type="error"')
        ->toContain('data-alert-message')
        ->not->toContain('client-container pt-4')
        ->not->toContain('border-rose-200')
        ->and($script)
        ->toContain("import Swal from 'sweetalert2'")
        ->toContain("document.querySelectorAll('[data-client-alert]')")
        ->toContain('void Swal.fire({')
        ->toContain("icon: 'error'")
        ->not->toContain("document.querySelectorAll('[data-client-toast]')")
        ->and($home)
        ->toContain('@if ($homeNoticeIsPublished && $homeNoticeHtml->isNotEmpty())')
        ->toContain('<div class="home-notice-banner" role="note"')
        ->toContain('class="home-notice-header"')
        ->toContain('{{ $homeNoticeTitle }}')
        ->toContain('{{ $homeNoticeHtml }}')
        ->not->toContain('<details class="home-notice-banner"')
        ->not->toContain('Xem chi tiết')
        ->and($controller)
        ->toContain("'home_notice_title'")
        ->toContain("'home_notice_content'")
        ->toContain("'home_notice_is_published'");
});
