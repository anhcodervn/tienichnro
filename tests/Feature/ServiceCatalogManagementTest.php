<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\ServiceOffering;
use App\Models\User;

function offeringPayload(array $extra = []): array
{
    return ['code' => 'game_notifications', 'name' => 'Dịch vụ riêng', 'description' => 'Dịch vụ có dữ liệu riêng',
        'url' => '/tin-tuc', 'icon_type' => 'icon', 'icon' => 'bx-store', 'image_url' => null,
        'is_enabled' => true, 'maintenance_message' => 'Dịch vụ đang bảo trì.', 'sort_order' => 1, ...$extra];
}

beforeEach(function (): void {
    config(['license.services_visible' => true]);
});

test('page slug rejects URLs traversal and invalid names', function (string $slug): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-catalog', offeringPayload(['page_slug' => $slug]))
        ->assertUnprocessable()->assertJsonValidationErrors('page_slug');
})->with(['/dich-vu-nhan-thong-bao', 'https://example.com', '../admin', 'Some_Page', 'a b']);

test('service navigation uses the configured page slug ahead of a previous URL', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-catalog', offeringPayload(['page_slug' => 'dich-vu-nhan-thong-bao']))
        ->assertCreated()->assertJsonPath('data.url', '/dich-vu-nhan-thong-bao');
    $this->get('/')->assertOk()->assertSee('href="/dich-vu-nhan-thong-bao"', false);
});

test('service CRUD uses independent SQL records even when its code matches a tool', function (): void {
    $toolBefore = app(ToolAvailabilityService::class)->find('game_notifications');
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/settings/service-catalog', offeringPayload())->assertCreated()
        ->assertJsonPath('data.code', 'game_notifications')->assertJsonPath('data.name', 'Dịch vụ riêng');
    $this->assertDatabaseHas('service_offerings', ['code' => 'game_notifications', 'name' => 'Dịch vụ riêng']);
    $this->getJson('/api/admin-api/settings/service-catalog')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin-api/settings/services')->assertOk()->assertJsonPath('data.0.name', $toolBefore['name']);
    $this->patchJson('/api/admin-api/settings/service-catalog/game_notifications', offeringPayload(['code' => 'changed', 'name' => 'Dịch vụ đã sửa', 'sort_order' => 0]))
        ->assertOk()->assertJsonPath('data.code', 'game_notifications')->assertJsonPath('data.name', 'Dịch vụ đã sửa');
    expect(app(ToolAvailabilityService::class)->find('game_notifications'))->toBe($toolBefore);
    $this->deleteJson('/api/admin-api/settings/service-catalog/game_notifications')->assertNoContent();
    $this->assertSoftDeleted('service_offerings', ['code' => 'game_notifications']);
    $this->getJson('/api/admin-api/settings/service-catalog')->assertOk()->assertJsonCount(0, 'data');
    $this->postJson('/api/admin-api/settings/service-catalog', offeringPayload())->assertUnprocessable()->assertJsonValidationErrors('code');
    expect(app(ToolAvailabilityService::class)->find('game_notifications'))->toBe($toolBefore);
});

test('public service modal has its own list and empty state independently of tools', function (): void {
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $modal = $xpath->query('//dialog[@id="client-services-modal"]')->item(0);
    expect($modal->textContent)->toContain('Chưa có dịch vụ được cấu hình.')->not->toContain('Thông báo game');
    ServiceOffering::factory()->create(['code' => 'shop', 'name' => 'Dịch vụ riêng', 'icon' => 'bx-store']);
    $response = $this->get('/')->assertOk()->assertViewHas('tools', fn ($tools): bool => ! $tools->contains('code', 'shop'));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//dialog[@id="client-services-modal"]')->item(0)->textContent)->toContain('Dịch vụ riêng')->not->toContain('Thông báo game');
    expect($xpath->query('//dialog[@id="client-tools-modal"]')->item(0)->textContent)->toContain('Thông báo game')->not->toContain('Dịch vụ riêng');
    $this->get('/dich-vu')->assertOk()->assertViewHas('services', fn ($services): bool => $services->pluck('code')->all() === ['shop']);
});

test('service catalog rejects unsafe and invalid values', function (array $extra, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-catalog', offeringPayload($extra))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('service_offerings', 0);
})->with([
    [['code' => '../bad'], 'code'], [['name' => ''], 'name'], [['sort_order' => -1], 'sort_order'],
    [['url' => 'javascript:alert(1)'], 'url'], [['url' => '//example.com'], 'url'],
    [['icon' => 'invalid'], 'icon'], [['icon_type' => 'image', 'image_url' => null], 'image_url'],
    [['icon_type' => 'image', 'image_url' => 'javascript:alert(1)'], 'image_url'],
]);

test('service catalog endpoints are protected and unknown services return not found', function (): void {
    foreach ([['GET', '/api/admin-api/settings/service-catalog'], ['POST', '/api/admin-api/settings/service-catalog'],
        ['PATCH', '/api/admin-api/settings/service-catalog/missing'], ['DELETE', '/api/admin-api/settings/service-catalog/missing']] as [$method, $uri]) {
        $this->json($method, $uri, offeringPayload())->assertUnauthorized();
    }
    $this->actingAs(User::factory()->create(['role' => 'user']));
    foreach ([['GET', '/api/admin-api/settings/service-catalog'], ['POST', '/api/admin-api/settings/service-catalog'],
        ['PATCH', '/api/admin-api/settings/service-catalog/missing'], ['DELETE', '/api/admin-api/settings/service-catalog/missing']] as [$method, $uri]) {
        $this->json($method, $uri, offeringPayload())->assertForbidden();
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/service-catalog/missing', offeringPayload())->assertNotFound();
    $this->deleteJson('/api/admin-api/settings/service-catalog/missing')->assertNotFound();
});

test('service image and disabled states render correctly with stable ordering', function (): void {
    ServiceOffering::factory()->create(['code' => 'image', 'name' => 'Dịch vụ ảnh', 'icon_type' => 'image', 'image_url' => '/service.webp', 'sort_order' => 0]);
    ServiceOffering::factory()->create(['code' => 'disabled', 'is_enabled' => false, 'url' => '/disabled', 'sort_order' => 10]);
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $modal = $xpath->query('//dialog[@id="client-services-modal"]')->item(0);
    expect($xpath->query('.//img[@src="/service.webp"]', $modal)->length)->toBe(1)
        ->and($xpath->query('.//a[@href="/disabled"]', $modal)->length)->toBe(0)
        ->and($xpath->query('.//button[@disabled]', $modal)->length)->toBe(1);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/settings/service-catalog')
        ->assertOk()->assertJsonPath('data.0.code', 'image')->assertJsonPath('data.1.code', 'disabled');
});

test('service packages reject tools that have no independent service record', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/admin-api/settings/service-packages', [
        'service_code' => 'game_notifications', 'name' => 'Package', 'price' => 10000, 'billing_type' => 'lifetime',
        'usage_limit' => null, 'duration_days' => null, 'is_active' => true, 'sort_order' => 1,
    ])->assertUnprocessable()->assertJsonValidationErrors('service_code');
});

test('admin service and tool pages use separate API clients without a group switch', function (): void {
    $servicePage = file_get_contents(resource_path('js/pages/admin/services/index.vue'));
    $toolPage = file_get_contents(resource_path('js/pages/admin/tools/index.vue'));
    $packagesPage = file_get_contents(resource_path('js/pages/admin/service-packages/index.vue'));
    expect($servicePage)->toContain('@/services/admin-service-catalog.service')->not->toContain('catalog_type', 'admin-service-management.service');
    expect($toolPage)->toContain('@/services/admin-service-management.service');
    expect($packagesPage)->toContain('adminServiceCatalog.list()')->not->toContain('adminServiceManagement.list()');
});
