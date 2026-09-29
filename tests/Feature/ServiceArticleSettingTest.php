<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;

test('only admins can manage game service navigation settings', function (): void {
    $this->getJson('/api/admin-api/settings/service-articles')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/service-articles', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/service-articles')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/service-articles', [])->assertForbidden();
});

test('game service navigation defaults to a hidden empty submenu', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/service-articles')
        ->assertOk()
        ->assertJsonPath('data.settings.game_service_enabled', false)
        ->assertJsonPath('data.settings.game_service_items', []);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/general')
        ->assertOk()
        ->assertJsonPath('data.settings.footer_game_links', []);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-game-service-menu', false)
        ->assertDontSee('data-footer-game-links', false);
});

test('admin can retain legacy game service links without rendering them in client navigation', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/service-articles', [
            'game_service_enabled' => true,
            'game_service_items' => [
                ['label' => '  Nạp Ngọc Rồng  ', 'url' => '  /bai-viet/nap-ngoc-rong  '],
                ['label' => 'Nạp Avatar', 'url' => 'https://dichvu.example.com/nap-avatar?source=nav'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.game_service_enabled', true)
        ->assertJsonPath('data.settings.game_service_items.0.label', 'Nạp Ngọc Rồng')
        ->assertJsonPath('data.settings.game_service_items.0.url', '/bai-viet/nap-ngoc-rong')
        ->assertJsonPath('data.settings.game_service_items.1.label', 'Nạp Avatar');

    $stored = Setting::query()->where('key', 'game_service_items')->firstOrFail();

    expect($stored->type)->toBe('json')
        ->and(json_decode((string) $stored->value, true))->toBe([
            ['label' => 'Nạp Ngọc Rồng', 'url' => '/bai-viet/nap-ngoc-rong'],
            ['label' => 'Nạp Avatar', 'url' => 'https://dichvu.example.com/nap-avatar?source=nav'],
        ]);

    $response = $this->get(route('home'))->assertOk();

    $response
        ->assertDontSee('href="/bai-viet/nap-ngoc-rong"', false)
        ->assertDontSee('href="https://dichvu.example.com/nap-avatar?source=nav"', false);
});

test('game service submenu disappears when disabled but keeps its configured items', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $items = [
        ['label' => 'Nạp Ninja School', 'url' => '/dich-vu/nap-ninja-school'],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/service-articles', [
            'game_service_enabled' => false,
            'game_service_items' => $items,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.game_service_items', $items);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-game-service-menu', false);
});

test('admin can configure safe footer links to other game topup pages', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $footerLinks = [
        ['label' => '  Nạp FC Online  ', 'url' => '  https://games.example.com/nap-fc-online  '],
        ['label' => 'Nạp Liên Quân', 'url' => '/nap-lien-quan'],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/general', [
            'site_name' => 'Nạp Carot',
            'site_domain' => 'https://napcarot.test',
            'site_description' => '',
            'site_active' => true,
            'allow_register' => true,
            'footer_game_links' => $footerLinks,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.footer_game_links.0.label', 'Nạp FC Online')
        ->assertJsonPath('data.settings.footer_game_links.0.url', 'https://games.example.com/nap-fc-online')
        ->assertJsonPath('data.settings.footer_game_links.1.url', '/nap-lien-quan');

    $stored = Setting::query()->where('key', 'footer_game_links')->firstOrFail();

    expect($stored->type)->toBe('json');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-footer-game-links', false)
        ->assertSee('Nạp game khác')
        ->assertSee('href="https://games.example.com/nap-fc-online"', false)
        ->assertSee('href="/nap-lien-quan"', false);
});

test('footer game links reject unsafe redirect urls', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/general', [
            'site_name' => 'Nạp Carot',
            'site_domain' => 'https://napcarot.test',
            'site_description' => '',
            'site_active' => true,
            'allow_register' => true,
            'footer_game_links' => [
                ['label' => 'Game xấu', 'url' => 'javascript:alert(1)'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['data' => ['errors' => ['footer_game_links.0.url']]]);
});

test('enabled game service submenu requires at least one item', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/service-articles', [
            'game_service_enabled' => true,
            'game_service_items' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['data' => ['errors' => ['game_service_items']]]);
});

test('game service submenu validates every label and link', function (array $item, string $field): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/service-articles', [
            'game_service_enabled' => true,
            'game_service_items' => [$item],
        ])
        ->assertUnprocessable()
        ->assertJsonStructure(['data' => ['errors' => [$field]]]);
})->with([
    'empty label' => [['label' => '', 'url' => '/dich-vu/game'], 'game_service_items.0.label'],
    'empty url' => [['label' => 'Dịch vụ', 'url' => ''], 'game_service_items.0.url'],
    'javascript' => [['label' => 'Dịch vụ', 'url' => 'javascript:alert(1)'], 'game_service_items.0.url'],
    'data' => [['label' => 'Dịch vụ', 'url' => 'data:text/html,<script>alert(1)</script>'], 'game_service_items.0.url'],
    'protocol relative' => [['label' => 'Dịch vụ', 'url' => '//evil.example.com/game'], 'game_service_items.0.url'],
    'backslash relative' => [['label' => 'Dịch vụ', 'url' => '/\\evil.example.com/game'], 'game_service_items.0.url'],
    'ftp' => [['label' => 'Dịch vụ', 'url' => 'ftp://example.com/game'], 'game_service_items.0.url'],
]);

test('client navigation ignores legacy service links written outside the admin endpoint', function (): void {
    app(SettingStore::class)->putMany([
        'game_service_enabled' => true,
        'game_service_items' => [
            ['label' => 'Liên kết an toàn', 'url' => '/dich-vu/an-toan'],
            ['label' => 'Liên kết xấu', 'url' => 'javascript:alert(1)'],
            ['label' => '<script>alert(2)</script>', 'url' => '/dich-vu/escape-label'],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('href="/dich-vu/an-toan"', false)
        ->assertDontSee('javascript:alert(1)', false)
        ->assertDontSee('<script>alert(2)</script>', false);
});

test('client navigation ignores a legacy single service link', function (): void {
    app(SettingStore::class)->putMany([
        'game_service_enabled' => true,
        'game_service_url' => '/dich-vu-cu',
    ]);

    $response = $this->get(route('home'))->assertOk();

    $response
        ->assertDontSee('href="/dich-vu-cu"', false)
        ->assertDontSee('data-game-service-menu', false);
});
