<?php

test('desktop header keeps primary actions visible and groups secondary destinations', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');

    preg_match('/<nav class="hidden[^>]+aria-label="Điều hướng chính">(?<navigation>.*?)<\/nav>/s', $layout, $matches);
    $desktopNavigation = $matches['navigation'] ?? '';
    $topupPosition = strpos($desktopNavigation, 'data-game-picker-trigger="desktop"');
    $gameServicePosition = strpos($desktopNavigation, 'data-game-service-picker-trigger="desktop"');
    $walletPosition = strpos($desktopNavigation, "route('wallet.deposit.index')");

    expect($desktopNavigation)
        ->not->toBeEmpty()
        ->toContain('bx bx-bolt text-lg')
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
        ->and($topupPosition)->not->toBeFalse()
        ->and($gameServicePosition)->not->toBeFalse()->toBeGreaterThan($topupPosition)
        ->and($walletPosition)->not->toBeFalse()->toBeGreaterThan($gameServicePosition)
        ->and(substr_count($desktopNavigation, 'data-desktop-nav-menu'))
        ->toBe(2)
        ->and($script)
        ->toContain("document.querySelectorAll('[data-desktop-nav-menu]')")
        ->toContain("document.querySelector('[data-game-service-picker-modal]')")
        ->toContain('if (otherMenu !== menu) otherMenu.open = false')
        ->toContain('if (!menu.contains(event.target)) menu.open = false')
        ->toContain("if (event.key !== 'Escape') return")
        ->toContain("openMenu.querySelector('summary')?.focus()");
});

test('desktop account profile uses the compact header trigger', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');

    preg_match('/<div class="relative" data-account-menu>\s*<button\s+type="button"\s+class="(?<classes>[^"]+)"/s', $layout, $matches);
    $triggerClasses = $matches['classes'] ?? '';

    expect($triggerClasses)
        ->not->toBeEmpty()
        ->toContain('min-h-10')
        ->toContain('w-[11rem]')
        ->toContain('gap-2')
        ->not->toContain('w-[15.5rem]')
        ->and($layout)
        ->toContain('grid h-7 w-7 shrink-0')
        ->toContain('id="client-account-menu" class="absolute right-0 top-full z-50 mt-2 w-72');
});
