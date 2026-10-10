<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

function serviceCrudPayload(array $overrides = []): array
{
    return array_replace(['code' => 'new_service', 'name' => 'Dịch vụ mới', 'description' => 'Mô tả mới', 'url' => '/tin-tuc', 'is_enabled' => true, 'maintenance_message' => 'Đang cập nhật.', 'icon_type' => 'icon', 'icon' => 'bx-joystick', 'image_url' => null], $overrides);
}

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('service creation and deletion require administrator access', function (): void {
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload())->assertUnauthorized();
    $this->deleteJson('/api/admin-api/settings/services/game_notifications')->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']));
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload())->assertForbidden();
    $this->deleteJson('/api/admin-api/settings/services/game_notifications')->assertForbidden();
});

test('custom services support create read update and delete without resetting existing settings', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/game_notifications', serviceCrudPayload(['name' => 'Boss tùy chỉnh', 'is_enabled' => false]))->assertOk();
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload())->assertCreated()->assertJsonPath('data.code', 'new_service')->assertJsonPath('data.name', 'Dịch vụ mới')->assertJsonPath('data.url', '/tin-tuc')->assertJsonPath('data.is_available', true);
    $this->getJson('/api/admin-api/settings/services')->assertOk()->assertJsonCount(count(config('tools.items')) + 1, 'data');
    $this->get('/')->assertOk()->assertSee('Dịch vụ mới')->assertSee('href="/tin-tuc"', false)->assertSee('Mô tả mới');
    $this->patchJson('/api/admin-api/settings/services/new_service', serviceCrudPayload(['name' => 'Dịch vụ đã sửa', 'code' => 'cannot_rename', 'description' => 'Mô tả đã sửa', 'url' => null]))->assertOk()->assertJsonPath('data.code', 'new_service')->assertJsonPath('data.name', 'Dịch vụ đã sửa')->assertJsonPath('data.is_available', false);
    $this->get('/')->assertOk()->assertSee('Dịch vụ đã sửa')->assertSee('Mô tả đã sửa')->assertSee('Sắp ra mắt');
    expect(app(ToolAvailabilityService::class)->find('game_notifications')['name'])->toBe('Boss tùy chỉnh');
    expect(app(ToolAvailabilityService::class)->find('game_notifications')['is_enabled'])->toBeFalse();
    $this->deleteJson('/api/admin-api/settings/services/new_service')->assertNoContent();
    $this->getJson('/api/admin-api/settings/services')->assertOk()->assertJsonCount(count(config('tools.items')), 'data');
    $this->get('/')->assertOk()->assertDontSee('Dịch vụ đã sửa');
    $this->deleteJson('/api/admin-api/settings/services/new_service')->assertNotFound();
    $this->patchJson('/api/admin-api/settings/services/new_service', serviceCrudPayload())->assertNotFound();
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload())->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('deleting a built in service hides its card and keeps its SEO page with interaction disabled', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $feed = app(NroNotificationFeedService::class)->events([], 1, 0);
    expect($feed->current()->event)->toBe('notifications');
    $this->deleteJson('/api/admin-api/settings/services/game_notifications')->assertNoContent();
    $this->get('/')->assertOk()->assertViewHas('tools', fn ($tools): bool => ! $tools->contains('code', 'game_notifications'));
    $this->get('/thong-bao-game')->assertOk()->assertSee('Thông báo game Ngọc Rồng Online')->assertSee('data-service-maintenance', false)->assertDontSee('data-nro-filters', false);
    $this->getJson('/api/nro/notifies')->assertStatus(503)->assertJsonPath('service_maintenance', true);
    $this->getJson('/api/nro/notifies/stream')->assertStatus(503);
    $feed->next();
    expect($feed->current()->event)->toBe('maintenance');
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Notice', 'occurred_at' => now()->toISOString()])->assertCreated();
    $this->getJson('/api/admin-api/nro/notifies')->assertOk()->assertJsonCount(1, 'data');
    $this->get('/tin-tuc')->assertOk();
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload(['code' => 'game_notifications']))->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('disabled custom URL services are not linked from either client tool list', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $url = 'https://example.com/private-tool';
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload(['url' => $url, 'is_enabled' => false]))->assertCreated();
    $response = $this->get('/')->assertOk()->assertSee('Dịch vụ mới')->assertSee('Bảo trì')->assertDontSee('href="'.$url.'"', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['data-home-tool-grid', 'data-client-tools-list'] as $attribute) {
        $buttons = $xpath->query('//*[@'.$attribute.']//button[@disabled]');
        expect($buttons->length)->toBe(4);
    }
});

test('service codes are unique and unregistered updates never create a definition', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = serviceCrudPayload();
    $this->postJson('/api/admin-api/settings/services', $payload)->assertCreated();
    $settingsCount = Setting::query()->count();
    $this->postJson('/api/admin-api/settings/services', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->patchJson('/api/admin-api/settings/services/unknown_service', $payload)->assertNotFound();
    $this->deleteJson('/api/admin-api/settings/services/unknown_service')->assertNotFound();
    $this->assertDatabaseMissing('settings', ['key' => 'service.definition.unknown_service']);
    $this->assertDatabaseCount('settings', $settingsCount);
});

test('disabled built in services keep the maintenance destination despite a custom URL', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $url = 'https://example.com/override';
    $this->patchJson('/api/admin-api/settings/services/game_notifications', serviceCrudPayload(['url' => $url, 'is_enabled' => false]))->assertOk();
    $this->get('/')->assertOk()->assertDontSee('href="'.$url.'"', false)->assertSee('href="'.route('nro.notifies.page').'"', false);
    $this->get('/thong-bao-game')->assertOk()->assertSee('data-service-maintenance', false);
});

test('new service invalid fields are rejected without leaving definitions', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(app(ToolAvailabilityService::class)->all())->toHaveCount(count(config('tools.items')));
})->with([
    'unsafe code' => [['code' => '../bad'], 'code'],
    'reserved code' => [['code' => 'game_notifications'], 'code'],
    'empty name' => [['name' => ''], 'name'],
    'bad URL' => [['url' => 'javascript:alert(1)'], 'url'],
    'protocol relative URL' => [['url' => '//example.com/tool'], 'url'],
    'long description' => [['description' => str_repeat('x', 501)], 'description'],
    'missing image' => [['icon_type' => 'image', 'image_url' => null], 'image_url'],
]);

test('service management uses the shared data table and modal CRUD controls', function (): void {
    $page = file_get_contents(resource_path('js/pages/admin/tools/index.vue'));
    expect($page)->toContain('<DataTable', '<Dialog', '<DialogTitle', 'adminServiceManagement.create', 'adminServiceManagement.remove', 'v-model="draft.code"', 'v-model="draft.url"', 'v-model="query"', 'v-model="status"', 'v-model.number="perPage"', 'showCancelButton: true');
    expect($page)->toContain('v-model.number="draft.sort_order"', "accessorKey: 'sort_order'", "ref('order_asc')");
});

test('service display order is saved as an integer in SQL and shared by admin and client lists', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload(['sort_order' => 0]))
        ->assertCreated()->assertJsonPath('data.sort_order', 0);
    $definition = json_decode(Setting::query()->where('key', 'service.definition.new_service')->value('value'), true);
    expect($definition['sort_order'])->toBe(0);
    $this->getJson('/api/admin-api/settings/services')->assertOk()->assertJsonPath('data.0.code', 'new_service');
    $this->get('/')->assertOk()->assertViewHas('tools', fn ($tools): bool => $tools->first()['code'] === 'new_service');

    $this->patchJson('/api/admin-api/settings/services/new_service', serviceCrudPayload(['sort_order' => 100]))
        ->assertOk()->assertJsonPath('data.sort_order', 100);
    $this->get('/')->assertOk()->assertViewHas('tools', fn ($tools): bool => $tools->last()['code'] === 'new_service');
    $this->patchJson('/api/admin-api/settings/services/new_service', serviceCrudPayload(['name' => 'Đổi tên']))
        ->assertOk()->assertJsonPath('data.sort_order', 100);
});

test('default service order is preserved and equal indexes keep existing relative order', function (): void {
    $services = app(ToolAvailabilityService::class);
    $codes = $services->all()->pluck('code')->all();
    expect($services->all()->pluck('sort_order')->all())->toBe(range(1, count($codes)));
    $services->update($codes[1], ['is_enabled' => true, 'maintenance_message' => 'Bảo trì', 'sort_order' => 0]);
    $services->update($codes[0], ['is_enabled' => true, 'maintenance_message' => 'Bảo trì', 'sort_order' => 0]);
    expect($services->all()->pluck('code')->take(2)->all())->toBe(array_slice($codes, 0, 2))
        ->and($services->all()->keys()->all())->toBe(range(0, count($codes) - 1));
});

test('service display indexes reject invalid input on create and update', function (mixed $order): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->postJson('/api/admin-api/settings/services', serviceCrudPayload(['sort_order' => $order]))
        ->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->patchJson('/api/admin-api/settings/services/game_notifications', serviceCrudPayload(['sort_order' => $order]))
        ->assertUnprocessable()->assertJsonValidationErrors('sort_order');
})->with([-1, 1.5, 'invalid', null, 1000001]);
