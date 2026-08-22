<?php

test('admin main content is consistently constrained to 1200 pixels', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/AdminLayout.vue');

    expect($layout)
        ->toContain('<main :class="isSupportRoute')
        ->toContain('class="mx-auto w-full min-w-0 max-w-[1200px]"')
        ->toContain('<router-view />');
});
