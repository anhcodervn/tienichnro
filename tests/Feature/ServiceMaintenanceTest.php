<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\SeoPost;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
});

test('only administrators can list and update registered services', function (): void {
    $payload = ['is_enabled' => false, 'maintenance_message' => 'Đang cập nhật dịch vụ.'];
    $this->getJson('/api/admin-api/settings/services')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload)->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']))->getJson('/api/admin-api/settings/services')->assertForbidden();
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->getJson('/api/admin-api/settings/services')->assertOk()->assertJsonCount(count(config('tools.items')), 'data')->assertJsonPath('data.0.code', 'game_notifications')->assertJsonPath('data.0.is_enabled', true);
    $this->patchJson('/api/admin-api/settings/services/unknown', $payload)->assertNotFound();
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => 'bad', 'maintenance_message' => str_repeat('x', 1001)])->assertUnprocessable()->assertJsonValidationErrors(['is_enabled', 'maintenance_message']);
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload)->assertOk()->assertJsonPath('data.is_enabled', false);
    $this->patchJson('/api/admin-api/settings/services/game_password', ['is_enabled' => false, 'maintenance_message' => 'Đổi mật khẩu đang bảo trì.'])->assertOk();
    $this->patchJson('/api/admin-api/settings/services/game_notifications', array_replace($payload, ['is_enabled' => true]))->assertOk();
    expect(app(ToolAvailabilityService::class)->find('game_password')['is_enabled'])->toBeFalse();
    $this->assertDatabaseMissing('settings', ['key' => 'tool_unknown_enabled']);
    expect(file_get_contents(resource_path('js/pages/admin/services/index.vue')))->toContain('v-model="draft.is_enabled"', 'v-model="draft.maintenance_message"', 'adminServiceManagement.update');
    expect(file_get_contents(resource_path('js/layouts/admin/sidebar/navigation.ts')))->toContain("href: '/admin/services'");
});

test('service maintenance replaces only client interaction and preserves SEO content and ingestion', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->get('/thong-bao-game')->assertOk()->assertSee('data-nro-filters', false);
    $message = 'Đang cập nhật <script>alert(1)</script>';
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => false, 'maintenance_message' => $message])->assertOk();
    $this->get('/thong-bao-game')->assertOk()->assertSee('Thông báo game Ngọc Rồng Online')->assertSee('data-service-maintenance', false)
        ->assertSee($message)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('data-nro-filters', false)->assertDontSee('data-nro-stream=', false)->assertDontSee('data-nro-list', false)
        ->assertSee('Theo dõi vòng đời Boss')->assertSee('rel="canonical"', false);
    foreach (['/api/nro/notifies', '/api/nro/notifies/stream', '/thong-bao-game'] as $path) {
        $this->getJson($path)->assertStatus(503)->assertJsonPath('service_maintenance', true)->assertJsonPath('message', $message)->assertHeader('Retry-After', '60');
    }
    $this->getJson('/api/nro/options')->assertOk()->assertJsonCount(22, 'data.servers');
    $this->getJson('/api/admin-api/nro/notifies')->assertOk();
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Notice during maintenance', 'occurred_at' => now()->toISOString()])->assertCreated();
    $post = SeoPost::query()->create(['title' => 'Hướng dẫn vẫn hiển thị', 'slug' => 'guide-maintenance', 'type' => 'guide', 'status' => 'published', 'published_at' => now()->subMinute(), 'robots' => 'index,follow', 'content' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung SEO vẫn hoạt động.']]]]]);
    $this->get(route('seo.legacy.show', ['slug' => $post->slug]))->assertOk()->assertSee($post->title)->assertSee('Nội dung SEO vẫn hoạt động.');
    $this->get('/')->assertOk()->assertSee('Tham gia cộng đồng')->assertSee('Bảo trì')->assertSee('href="'.route('nro.notifies.page').'"', false);
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => true, 'maintenance_message' => $message])->assertOk();
    $this->get('/thong-bao-game')->assertOk()->assertSee('data-nro-filters', false)->assertDontSee('data-service-maintenance', false);
    $this->getJson('/api/nro/notifies')->assertOk()->assertJsonCount(1, 'data');
});

test('existing SSE streams detect maintenance changes and stop sending notifications', function (): void {
    $feed = app(NroNotificationFeedService::class)->events([], 1, 0);
    expect($feed->current()->event)->toBe('notifications');
    app(ToolAvailabilityService::class)->update('game_notifications', ['is_enabled' => false, 'maintenance_message' => 'Đang cập nhật.']);
    $feed->next();
    expect($feed->current()->event)->toBe('maintenance')->and($feed->current()->data)->toBe(['message' => 'Đang cập nhật.']);
    $feed->next();
    expect($feed->valid())->toBeFalse();
});

test('unimplemented services remain coming soon when enabled and show maintenance when disabled', function (): void {
    $services = app(ToolAvailabilityService::class);
    expect($services->find('game_password')['is_available'])->toBeFalse();
    $this->get('/')->assertOk()->assertSee('Sắp ra mắt');
    $services->update('game_password', ['is_enabled' => false, 'maintenance_message' => 'Tạm tắt đổi mật khẩu.']);
    $this->get('/')->assertOk()->assertSee('Bảo trì')->assertSee('Sắp ra mắt')->assertSee('data-home-tool-grid', false);
    expect($services->find('game_notifications')['is_enabled'])->toBeTrue();
});

test('service names and icons can be customized without changing stable codes routes or SEO', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['is_enabled' => true, 'maintenance_message' => 'Đang bảo trì.', 'name' => 'Theo dõi Boss <script>x</script>', 'icon_type' => 'icon', 'icon' => 'bx-joystick', 'image_url' => null];
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload)->assertOk()->assertJsonPath('data.name', $payload['name'])->assertJsonPath('data.icon', 'bx-joystick')->assertJsonPath('data.code', 'game_notifications')->assertJsonPath('data.route', 'nro.notifies.page');
    $this->get('/')->assertOk()->assertSee($payload['name'])->assertDontSee('<script>x</script>', false)->assertSee('class="bx bx-joystick"', false)->assertSee('href="'.route('nro.notifies.page').'"', false);
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => false, 'maintenance_message' => 'Bảo trì mới.'])->assertOk()->assertJsonPath('data.name', $payload['name'])->assertJsonPath('data.icon', 'bx-joystick')->assertJsonPath('data.icon_type', 'icon');
    $this->get('/thong-bao-game')->assertOk()->assertSee('Thông báo game Ngọc Rồng Online')->assertSee('data-service-maintenance', false);
});

test('service square images render in home and modal with bottom status badges', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['is_enabled' => false, 'maintenance_message' => 'Đang bảo trì.', 'name' => 'Boss realtime', 'icon_type' => 'image', 'image_url' => '/storage/uploads/boss.webp'];
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload)->assertOk()->assertJsonPath('data.image_url', $payload['image_url']);
    $this->patchJson('/api/admin-api/settings/services/game_password', array_replace($payload, ['name' => 'Mật khẩu', 'is_enabled' => true]))->assertOk();
    $response = $this->get('/')->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    foreach (['data-home-tool-grid', 'data-client-tools-list'] as $attribute) {
        $grid = $xpath->query('//*[@'.$attribute.']')->item(0);
        expect($xpath->query('.//img', $grid)->length)->toBe(2);
        $image = $xpath->query('.//a/span/img', $grid)->item(0);
        expect($image->getAttribute('src'))->toBe($payload['image_url'])->and($image->getAttribute('class'))->toContain('object-cover')
            ->and($image->parentNode->getAttribute('class'))->toContain('aspect-square');
        $badge = $xpath->query('.//a/span/span', $grid)->item(0);
        expect(trim($badge->textContent))->toBe('Bảo trì')->and($badge->getAttribute('class'))->toContain('bottom-2');
        expect($xpath->query('.//button/span/span', $grid)->item(0)->textContent)->toContain('Sắp ra mắt');
    }
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => true, 'maintenance_message' => 'Đang bảo trì.'])->assertOk()->assertJsonPath('data.icon_type', 'image')->assertJsonPath('data.image_url', $payload['image_url']);
    $this->patchJson('/api/admin-api/settings/services/game_notifications', array_replace($payload, ['is_enabled' => true, 'icon_type' => 'icon', 'icon' => 'bx-bell', 'image_url' => null]))->assertOk()->assertJsonPath('data.image_url', null);
    $this->get('/')->assertOk()->assertSee('class="bx bx-bell"', false);
    expect(file_get_contents(resource_path('js/pages/admin/services/index.vue')))->toContain('v-model="draft.name"', 'v-model="draft.icon_type"', ':square-size="256"', 'v-model="draft.image_url"');
});

test('unsafe or incomplete service visuals are rejected', function (array $visual, string $field): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/game_notifications', ['is_enabled' => true, 'maintenance_message' => 'Maintenance'] + $visual)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('x', 81)], 'name'],
    'bad mode' => [['icon_type' => 'svg'], 'icon_type'],
    'missing icon' => [['icon_type' => 'icon'], 'icon'],
    'arbitrary classes' => [['icon_type' => 'icon', 'icon' => 'bx-bell hidden'], 'icon'],
    'markup icon' => [['icon' => '"><script>x</script>'], 'icon'],
    'missing image' => [['icon_type' => 'image'], 'image_url'],
    'data url' => [['icon_type' => 'image', 'image_url' => 'data:image/svg+xml,<svg/>'], 'image_url'],
    'javascript url' => [['icon_type' => 'image', 'image_url' => 'javascript:alert(1)'], 'image_url'],
    'protocol relative' => [['icon_type' => 'image', 'image_url' => '//example.com/image.png'], 'image_url'],
]);

test('image mode cannot lose its image through a partial update', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $payload = ['is_enabled' => true, 'maintenance_message' => 'Maintenance'];
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload + ['icon_type' => 'image', 'image_url' => 'https://example.com/image.png'])->assertOk();
    $this->patchJson('/api/admin-api/settings/services/game_notifications', $payload + ['image_url' => null])->assertUnprocessable()->assertJsonValidationErrors('image_url');
});
