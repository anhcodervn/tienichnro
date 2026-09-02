<?php

test('global package inputs stay visually distinct and easy to focus', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/topup/global-packages/index.vue');

    expect($page)
        ->toContain('const formFieldClass =')
        ->toContain('const compactFieldClass =')
        ->toContain('type NumericDraft = string | number')
        ->toContain('const isBlankNumber = (value: NumericDraft)')
        ->toContain('const numberOr = (value: NumericDraft')
        ->toContain('border-2 border-slate-300 bg-slate-50')
        ->toContain('hover:border-slate-400')
        ->toContain('focus:border-violet-500 focus:bg-white focus:ring-4 focus:ring-violet-100')
        ->toContain('v-model="form.provider_id"')
        ->toContain('v-model="form.provider_price"')
        ->not->toContain('v-model="form.original_price"')
        ->not->toContain('Giá gốc hiển thị')
        ->not->toContain('draft.fixed_price.trim()')
        ->not->toContain('draft.minimum_profit.trim()')
        ->toContain('v-model.number="form.min_quantity"')
        ->toContain(':class="formFieldClass"')
        ->toContain(':class="compactFieldClass"')
        ->not->toContain('game_settings')
        ->not->toContain('KM X3')
        ->not->toMatch('/<(?:input|select|textarea)[^>]*class="[^"]*border-slate-300/');
});
