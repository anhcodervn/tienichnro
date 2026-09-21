<?php

test('provider price matrix uses read-only cells and a shared selection modal', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $page = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/provider-prices/index.vue');
    $cell = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/provider-prices/components/ProviderPriceCell.vue');
    $modal = file_get_contents($projectRoot.'/resources/js/pages/admin/topup/provider-prices/components/ProviderPriceModal.vue');

    expect($page)
        ->toContain("import ProviderPriceCell from './components/ProviderPriceCell.vue'")
        ->toContain("import ProviderPriceModal from './components/ProviderPriceModal.vue'")
        ->toContain('Loại gói / Tên gói')
        ->toContain('Hiện tại')
        ->toContain('Làm mới giá')
        ->not->toContain('v-model.number="row.provider_prices');

    expect($cell)
        ->toContain('Đang dùng')
        ->toContain('Rẻ nhất')
        ->toContain('Lãi dự kiến')
        ->toContain('Tiết kiệm +')
        ->toContain('Cao hơn +')
        ->toContain('Chọn nguồn');

    expect($modal)
        ->toContain("import Modal from '@/components/shared/Modal/index.vue'")
        ->toContain('Chọn nguồn provider')
        ->toContain('Giá nguồn')
        ->toContain('Giá bán mới')
        ->toContain('Biên lợi nhuận')
        ->toContain('Chọn nguồn & lưu');
});
