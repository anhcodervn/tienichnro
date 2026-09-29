<?php

test('admin game service menu exposes all management pages', function (): void {
    $navigation = file_get_contents(resource_path('js/layouts/admin/sidebar/navigation.ts'));
    $router = file_get_contents(resource_path('js/router/modules/admin/index.ts'));

    expect($navigation)
        ->toContain("key: 'game-services'")
        ->toContain("label: 'Dịch vụ game'")
        ->toContain("{ label: 'Quản lý game', href: '/admin/game-services/games' }")
        ->toContain("{ label: 'Quản lý dịch vụ', href: '/admin/game-services/services' }")
        ->toContain("{ label: 'Gói dịch vụ', href: '/admin/game-services/packages' }")
        ->toContain("{ label: 'Quản lý đơn order', href: '/admin/game-services/orders' }")
        ->and($router)
        ->toContain("name: 'admin.game-services.games'")
        ->toContain("name: 'admin.game-services.services'")
        ->toContain("name: 'admin.game-services.packages'")
        ->toContain("name: 'admin.game-services.orders'");
});

test('admin game service pages connect catalog payload prices servers and orders', function (): void {
    $catalog = file_get_contents(resource_path('js/pages/admin/game-services/catalog/index.vue'));
    $orders = file_get_contents(resource_path('js/pages/admin/game-services/orders/index.vue'));
    $service = file_get_contents(resource_path('js/services/admin-game-service.service.ts'));

    expect($catalog)
        ->toContain('game_services_enabled')
        ->toContain("import DataTable from '@/components/shared/DataTable/index.vue'")
        ->toContain("import Modal from '@/components/shared/Modal/index.vue'")
        ->toContain('<Modal v-model="serviceModalOpen"')
        ->toContain('<Modal v-model="packageModalOpen"')
        ->toContain('<DataTable')
        ->toContain('openCreateServiceModal()')
        ->toContain('openCreatePackageModal()')
        ->toContain('serviceForm.background_image')
        ->toContain('@uploaded="serviceForm.background_image = $event"')
        ->toContain(':src="row.background_image"')
        ->toContain('payload_fields')
        ->toContain('serviceForm.seo_content')
        ->toContain('serviceForm.faqs')
        ->toContain('Bài SEO dịch vụ')
        ->toContain('<option value="password">Password</option>')
        ->toContain('server_ids')
        ->toContain('packageForm.prices')
        ->toContain('packageForm.prices.slice(0, 1)')
        ->toContain('Cấu hình giá')
        ->not->toContain('packageForm.prices.push(blankPrice())')
        ->not->toContain('packageForm.prices.splice')
        ->toContain(':data="displayedPackageRows"')
        ->toContain('v-model="packageGameFilter"')
        ->toContain('item.service.game_id === Number(packageGameFilter.value)')
        ->toContain('Tất cả game')
        ->toContain('v-if="catalogType !== \'packages\'"')
        ->toContain('placeholder="Nhập tên hoặc mã gói..."')
        ->toContain('@click="clearPackageFilters"')
        ->toContain(':columns="packageColumns"')
        ->toContain('form="game-service-package-form"')
        ->toContain('v-model="packageForm.game_id"')
        ->toContain('v-model="packageForm.game_service_id"')
        ->toContain('v-for="service in availablePackageServices"')
        ->toContain(':disabled="!packageForm.game_id"')
        ->toContain('price.collaborator_price')
        ->toContain('price.quantity_enabled')
        ->toContain('price.quantity_enabled ? Number(price.min_quantity) : 1')
        ->not->toContain('v-model.trim="price.code"')
        ->not->toContain('v-model="price.status"')
        ->not->toContain('price.status ===')
        ->not->toContain('Cho phép đặt')
        ->not->toContain('v-model.trim="price.label"')
        ->toContain('saveService')
        ->toContain('savePackage')
        ->and($orders)
        ->toContain('selectedOrder.payload')
        ->toContain('saveOrder')
        ->toContain('admin_note')
        ->and($service)
        ->toContain("'/api/admin-api/game-service-games'")
        ->toContain("'/api/admin-api/game-services'")
        ->toContain("'/api/admin-api/game-service-packages'")
        ->toContain("'/api/admin-api/game-service-orders'")
        ->toContain('background_image: string | null')
        ->toContain("type: 'text' | 'number' | 'password' | 'select'");
});
