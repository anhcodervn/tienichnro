<?php

test('member level inputs stay visually distinct and easy to focus', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/member-levels/index.vue');

    expect($page)
        ->toContain('const formFieldClass =')
        ->toContain('const compactFieldClass =')
        ->toContain('border-2 border-slate-300 bg-slate-50')
        ->toContain('hover:border-slate-400')
        ->toContain('focus:border-amber-500 focus:bg-white focus:ring-4 focus:ring-amber-100')
        ->toContain(':class="formFieldClass"')
        ->toContain(':class="[compactFieldClass,')
        ->not->toMatch('/<(?:input|select|textarea)[^>]*class="[^"]*border-slate-300/');
});
