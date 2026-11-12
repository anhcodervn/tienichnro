<?php

test('admin main content expands on large screens and is constrained to 1800 pixels', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/js/layouts/AdminLayout.vue');

    expect($layout)
        ->toContain('id="admin-main"')
        ->toContain('lg:pl-[264px]')
        ->toContain('class="mx-auto w-full min-w-0 max-w-[1800px]"')
        ->toContain('data-admin-page-surface')
        ->toContain('rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6')
        ->toContain('<router-view />');
});
