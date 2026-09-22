<?php

test('game editor owns the per-account quantity limits', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/topup/catalog/index.vue');

    expect($page)
        ->toContain('Giới hạn số lượng cho 1 tài khoản')
        ->toContain('SL / tài khoản')
        ->toContain('Áp dụng chung cho mọi gói của game')
        ->toContain('v-model.number="form.min_quantity"')
        ->toContain('v-model.number="form.max_quantity"')
        ->toContain(':min="Number(form.min_quantity || 1)"')
        ->toContain('min_quantity: Number(form.min_quantity)')
        ->toContain('max_quantity: Number(form.max_quantity)')
        ->toContain('{{ row.min_quantity }}–{{ row.max_quantity }}');

    expect(substr_count($page, 'v-model.number="form.min_quantity"'))->toBe(1)
        ->and(substr_count($page, 'v-model.number="form.max_quantity"'))->toBe(1);
});
