<?php

test('game picker navigation is shared and keyboard accessible', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $modal = file_get_contents($projectRoot.'/resources/views/components/client/game-picker-modal.blade.php');
    $script = file_get_contents($projectRoot.'/resources/js/client.js');
    $styles = file_get_contents($projectRoot.'/resources/css/client.css');
    $desktopPickerPosition = strpos($layout, 'data-game-picker-trigger="desktop"');
    $desktopServicePickerPosition = strpos($layout, 'data-game-service-picker-trigger="desktop"');
    $desktopWalletPosition = strpos($layout, "route('wallet.deposit.index')", $desktopPickerPosition);
    $mobilePickerPosition = strpos($layout, 'data-game-picker-trigger="mobile-menu"');
    $mobileServicePickerPosition = strpos($layout, 'data-game-service-picker-trigger="mobile-menu"');
    $mobileFirstLinkPosition = strpos($layout, '<a data-menu-item', $mobilePickerPosition);
    $mobileBottomTopupPosition = strpos($layout, 'data-game-picker-trigger="mobile-bottom"');
    $mobileBottomServicePosition = strpos($layout, 'data-game-service-picker-trigger="mobile-bottom"');

    expect($layout)
        ->toContain('data-game-picker-trigger="desktop"')
        ->toContain('data-game-picker-trigger="mobile-menu"')
        ->toContain('data-game-picker-trigger="mobile-bottom"')
        ->toContain('data-game-service-picker-trigger="mobile-bottom"')
        ->toContain('data-mobile-nav-item="service"')
        ->toContain("'grid-cols-5' => \$showGameServicePicker")
        ->toContain('bx bx-bolt text-lg')
        ->toContain('bx bx-bolt text-xl')
        ->toContain('<x-client.game-picker-modal :games="$navigationGames ?? collect()" />')
        ->and(substr_count($layout, 'data-game-picker-open'))
        ->toBe(3)
        ->and($desktopServicePickerPosition)
        ->toBeGreaterThan($desktopPickerPosition)
        ->toBeLessThan($desktopWalletPosition)
        ->and($desktopPickerPosition)
        ->toBeLessThan($desktopWalletPosition)
        ->and($mobilePickerPosition)
        ->toBeLessThan($mobileFirstLinkPosition)
        ->and($mobileServicePickerPosition)
        ->toBeGreaterThan($mobilePickerPosition)
        ->toBeLessThan($mobileFirstLinkPosition)
        ->and($mobileBottomServicePosition)
        ->toBeGreaterThan($mobileBottomTopupPosition)
        ->and($home)
        ->toContain('data-home-game-grid')
        ->toContain('grid-cols-3 gap-x-3 gap-y-5')
        ->toContain('lg:grid-cols-8')
        ->toContain('aspect-square w-full max-w-[7.5rem]')
        ->toContain('line-clamp-2 min-h-10 w-full text-sm')
        ->not->toContain('>Nạp ngay</span>')
        ->and($modal)
        ->toContain('id="game-picker-modal"')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('aria-labelledby="game-picker-title"')
        ->toContain('data-game-picker-backdrop')
        ->toContain('data-game-picker-link')
        ->toContain('grid-cols-3 gap-x-3 gap-y-5')
        ->toContain('md:grid-cols-6')
        ->toContain('aspect-square w-full max-w-[7rem]')
        ->toContain("route('topup.game', ['game' => \$game])")
        ->and($script)
        ->toContain("document.querySelector('[data-game-picker-modal]')")
        ->toContain("menu.querySelectorAll('[data-game-picker-open]')")
        ->toContain("if (event.key === 'Escape')")
        ->toContain("if (event.key !== 'Tab') return")
        ->toContain('gamePickerReturnFocus.focus({ preventScroll: true })')
        ->toContain("document.body.classList.add('client-game-picker-open')")
        ->toContain("document.body.classList.remove('client-game-picker-open')")
        ->and($styles)
        ->toContain('body.client-game-picker-open');
});
