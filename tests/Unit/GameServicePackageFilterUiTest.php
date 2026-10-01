<?php

test('package catalog filters services within the selected game', function (): void {
    $projectRoot = dirname(__DIR__, 2);
    $source = file_get_contents($projectRoot.'/resources/js/pages/admin/game-services/catalog/index.vue');

    expect($source)
        ->toContain("const packageGameFilter = ref<number | ''>('');")
        ->toContain("const packageServiceFilter = ref<number | ''>('');")
        ->toContain('const packageFilterServices = computed(() =>')
        ->toContain('service.game_id === Number(packageGameFilter.value)')
        ->toContain('item.game_service_id === Number(packageServiceFilter.value)')
        ->toContain('v-model="packageGameFilter"')
        ->toContain('v-model="packageServiceFilter"')
        ->toContain(':disabled="packageGameFilter === \'\'"')
        ->toContain("packageServiceFilter.value = '';")
        ->toContain("packageGameFilter === '' ? 'Chọn game trước' : 'Tất cả dịch vụ'");
});
