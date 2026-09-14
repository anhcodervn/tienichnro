<?php

test('home page only uses icons available in the bundled boxicons font', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $home = file_get_contents($projectRoot.'/resources/views/client/home/index.blade.php');
    $iconStyles = file_get_contents($projectRoot.'/public/assets/icon/boxicons/fonts/basic/boxicons.min.css');

    preg_match_all('/\bbx-[a-z0-9-]+\b/', $home, $matches);

    foreach (array_unique($matches[0]) as $icon) {
        expect($iconStyles)->toContain(".{$icon}:before");
    }
});
