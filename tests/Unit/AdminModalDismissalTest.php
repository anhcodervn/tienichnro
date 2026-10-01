<?php

test('admin modals can only be dismissed with explicit controls', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $modalSources = [
        file_get_contents($projectRoot.'/resources/js/components/shared/Modal/index.vue'),
        file_get_contents($projectRoot.'/resources/js/components/shared/SecondaryPasswordDialog.vue'),
    ];
    $adminFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($projectRoot.'/resources/js/pages/admin', FilesystemIterator::SKIP_DOTS),
    );

    foreach ($adminFiles as $file) {
        if ($file->isFile() && $file->getExtension() === 'vue') {
            $modalSources[] = file_get_contents($file->getPathname());
        }
    }

    expect($modalSources)
        ->each->not->toContain('@click.self=')
        ->and($modalSources[0])
        ->not->toContain("e.key === 'Escape'")
        ->toContain('@click="close"');
});
