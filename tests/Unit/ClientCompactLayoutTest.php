<?php

test('client main content is fifteen percent smaller while containers retain their full visual width', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $styles = file_get_contents($projectRoot.'/resources/css/client.css');
    $toolPage = file_get_contents($projectRoot.'/resources/views/components/client/tool-page.blade.php');
    $toolHero = file_get_contents($projectRoot.'/resources/views/components/client/tool-hero.blade.php');

    expect($layout)
        ->toContain('<main id="main-content" class="min-w-0 focus:outline-none"')
        ->not->toContain('client-page-scale')
        ->and($home)
        ->toContain('<div class="client-container grid gap-4 py-4 sm:gap-5 sm:py-6">')
        ->not->toContain('lg:max-w-[50.4rem]')
        ->and($styles)
        ->not->toContain('.client-page-scale', 'max-width: 61.2rem;')
        ->toContain('max-w-6xl')
        ->toContain('--client-main-scale: 0.85;', 'zoom: var(--client-main-scale);')
        ->toContain('width: 100%;', 'max-width: calc(72rem / var(--client-main-scale));')
        ->not->toContain('width: calc(100% / var(--client-main-scale));')
        ->toContain('padding-inline: calc(0.75rem / var(--client-main-scale));', 'padding-inline: calc(1.5rem / var(--client-main-scale));', 'padding-inline: calc(2rem / var(--client-main-scale));')
        ->and($toolPage)
        ->toContain('class="client-container py-6 sm:py-8"', 'class="grid w-full min-w-0 grid-cols-1 gap-6 sm:gap-8"')
        ->not->toContain('max-w-4xl');

    expect($toolHero)->toContain('text-[22px]', 'sm:text-[26px]')
        ->and($home)->toContain('text-[22px]', 'sm:text-[26px]')
        ->and($styles)->toContain('.article-content[data-seo-content]', '@apply text-sm leading-7 sm:text-[15px];');
});
