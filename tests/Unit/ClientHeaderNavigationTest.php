<?php

test('desktop header keeps primary actions visible and groups secondary destinations', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');

    preg_match('/<nav class="hidden[^>]+aria-label="Điều hướng chính">(?<navigation>.*?)<\/nav>/s', $layout, $matches);
    $desktopNavigation = $matches['navigation'] ?? '';

    expect($desktopNavigation)
        ->not->toBeEmpty()
        ->toContain('<span>Nạp tiền</span>')
        ->toContain('<span>Đơn hàng</span>')
        ->toContain('<span>Dịch vụ game</span>')
        ->toContain('<span>Khám phá</span>')
        ->toContain("route('client.affiliate.spa')")
        ->toContain("route('seo.index')")
        ->toContain("route('content.guide')")
        ->toContain("route('content.contact')")
        ->toContain('data-support-icon')
        ->toContain('<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />')
        ->not->toContain('<span>Trang chủ</span>')
        ->not->toContain('<span>Lịch sử đơn hàng</span>')
        ->and(substr_count($desktopNavigation, 'data-desktop-nav-menu'))
        ->toBe(2)
        ->and($script)
        ->toContain("document.querySelectorAll('[data-desktop-nav-menu]')")
        ->toContain('if (otherMenu !== menu) otherMenu.open = false')
        ->toContain('if (!menu.contains(event.target)) menu.open = false')
        ->toContain("if (event.key !== 'Escape') return")
        ->toContain("openMenu.querySelector('summary')?.focus()");
});
