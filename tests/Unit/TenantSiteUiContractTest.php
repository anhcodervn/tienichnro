<?php

test('site management uses a filtered data table and create edit modal', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/sites/index.vue');

    expect($page)
        ->toContain("import DataTable from '@/components/shared/DataTable/index.vue'")
        ->toContain("import Modal from '@/components/shared/Modal/index.vue'")
        ->toContain('<DataTable')
        ->toContain('<Modal v-model="modalOpen"')
        ->toContain('filters.search')
        ->toContain('filters.site_type')
        ->toContain('filters.status')
        ->toContain('filters.per_page')
        ->toContain('billing_balance')
        ->toContain('orders_today_count')
        ->toContain('orders_month_count')
        ->toContain('Số dư NapCarot')
        ->toContain('Hôm nay:')
        ->toContain('Tháng này:')
        ->toContain('openCreateModal')
        ->toContain('openEditModal')
        ->toContain('editingSite.value.is_main')
        ->toContain('const formInputClass =')
        ->toContain('border-2 border-slate-300 bg-slate-50')
        ->toContain('hover:border-slate-400')
        ->toContain('focus:border-blue-500')
        ->toContain('focus:ring-4 focus:ring-blue-100')
        ->toContain('adminUserService.list')
        ->toContain('billingUserSearch')
        ->toContain('Nhập ID, username, email hoặc số điện thoại')
        ->toContain('được sao chép thành admin của website mới')
        ->not->toContain('admin_username')
        ->not->toContain('admin_email')
        ->not->toContain('admin_password')
        ->and($page)->toContain('border-2 border-slate-400 text-blue-600');
});

test('site price controls have visible interactive borders', function (): void {
    $page = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/admin/site-prices/index.vue');

    expect($page)
        ->toContain('const formControlClass =')
        ->toContain('border-2 border-slate-300 bg-slate-50')
        ->toContain('hover:border-slate-400')
        ->toContain('focus:border-emerald-500')
        ->toContain('focus:ring-4 focus:ring-emerald-100')
        ->toContain(":class=\"[formControlClass, 'min-w-44']\"")
        ->toContain(":class=\"[formControlClass, 'w-36']\"");
});
