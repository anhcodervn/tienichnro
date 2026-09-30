<?php

test('client blade pages use responsive compact sizing without narrowing the page', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $layout = file_get_contents($projectRoot.'/resources/views/client/layouts/app.blade.php');
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $styles = file_get_contents($projectRoot.'/resources/css/client.css');

    expect($layout)
        ->toContain('<main id="main-content" class="client-page-scale min-w-0 focus:outline-none"')
        ->and($home)
        ->toContain('<div class="client-container grid gap-4 py-4 sm:gap-5 sm:py-6">')
        ->not->toContain('lg:max-w-[50.4rem]')
        ->and($styles)
        ->toContain('width: 111.111111111%;')
        ->toContain('zoom: 0.9;')
        ->toContain('@media (min-width: 1024px)')
        ->toContain('.client-page-scale {')
        ->toContain('width: 117.647058824%;')
        ->toContain('zoom: 0.85;')
        ->toContain('.client-page-scale .client-container {')
        ->toContain('max-width: 84.705882353rem;');
});
