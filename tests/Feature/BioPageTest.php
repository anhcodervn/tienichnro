<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;

test('bio page is a standalone blade page with a home link', function (): void {
    app(SettingStore::class)->putMany([
        'site_name' => 'Nạp Carot',
        'bio_title' => 'Cộng đồng Nạp Carot',
        'bio_description' => 'Tất cả liên kết chính thức tại một nơi.',
        'bio_links' => [],
    ]);

    $this->get(route('bio.show'))
        ->assertOk()
        ->assertViewIs('pages.bio')
        ->assertSee('Cộng đồng Nạp Carot')
        ->assertSee('Tất cả liên kết chính thức tại một nơi.')
        ->assertSee('Quay về trang chủ')
        ->assertSee('href="'.route('home').'"', false);

    expect(route('bio.show', [], false))->toBe('/community');
});

test('homepage shows the community call to action without the topup form', function (): void {
    $response = $this->get(route('home'))->assertOk();

    $response
        ->assertSee('data-community-cta', false)
        ->assertSee('Tham gia cộng đồng')
        ->assertSee('Nổi bật')
        ->assertSee('href="'.route('bio.show').'"', false)
        ->assertDontSee('data-topup-form', false);
});

test('only admins can manage bio settings', function (): void {
    $payload = [
        'bio_title' => 'Nạp Carot',
        'bio_description' => '',
        'bio_avatar_url' => '',
        'bio_links' => [],
    ];

    $this->getJson('/api/admin-api/settings/bio')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/bio', $payload)->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/bio')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/bio', $payload)->assertForbidden();
});

test('admin can customize bio links and only active links are rendered safely', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = [
        'bio_title' => ' Nạp Carot Official ',
        'bio_description' => ' Kênh chính thức ',
        'bio_avatar_url' => '/images/avatar.webp',
        'bio_links' => [
            ['label' => ' Facebook chính thức ', 'url' => ' https://facebook.com/napcarot ', 'icon' => 'facebook', 'is_active' => true],
            ['label' => '<script>alert(1)</script>', 'url' => '/khuyen-mai', 'icon' => 'website', 'is_active' => true],
            ['label' => 'Link đang ẩn', 'url' => 'https://example.com/hidden', 'icon' => 'link', 'is_active' => false],
        ],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/bio', $payload)
        ->assertOk()
        ->assertJsonPath('data.settings.bio_title', 'Nạp Carot Official')
        ->assertJsonPath('data.settings.bio_links.0.label', 'Facebook chính thức');

    expect(Setting::query()->where('key', 'bio_links')->value('type'))->toBe('json');

    $this->get(route('bio.show'))
        ->assertOk()
        ->assertSee('Facebook chính thức')
        ->assertSee('href="https://facebook.com/napcarot"', false)
        ->assertSee('class="bxl bx-facebook-circle"', false)
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Link đang ẩn');
});

test('bio settings reject unsafe and duplicate links', function (array $links, string $field): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/bio', [
            'bio_title' => 'Nạp Carot',
            'bio_description' => '',
            'bio_avatar_url' => '',
            'bio_links' => $links,
        ])
        ->assertUnprocessable();

    expect(array_key_exists($field, $response->json('data.errors')))->toBeTrue();
})->with([
    'javascript URL' => [
        [['label' => 'Không an toàn', 'url' => 'javascript:alert(1)', 'icon' => 'link', 'is_active' => true]],
        'bio_links.0.url',
    ],
    'protocol relative URL' => [
        [['label' => 'Không an toàn', 'url' => '//example.com', 'icon' => 'link', 'is_active' => true]],
        'bio_links.0.url',
    ],
    'duplicate URL' => [
        [
            ['label' => 'Link 1', 'url' => 'https://example.com', 'icon' => 'link', 'is_active' => true],
            ['label' => 'Link 2', 'url' => 'https://example.com', 'icon' => 'link', 'is_active' => true],
        ],
        'bio_links.1.url',
    ],
]);

test('bio settings reject icons outside the supported list', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/bio', [
            'bio_title' => 'Nạp Carot',
            'bio_description' => '',
            'bio_avatar_url' => '',
            'bio_links' => [
                ['label' => 'Link', 'url' => 'https://example.com', 'icon' => 'bx-malicious-class', 'is_active' => true],
            ],
        ])
        ->assertUnprocessable();

    expect(array_key_exists('bio_links.0.icon', $response->json('data.errors')))->toBeTrue();
});

test('legacy bio links without an icon use the default link icon', function (): void {
    app(SettingStore::class)->putMany([
        'bio_links' => [
            ['label' => 'Liên kết cũ', 'url' => '/lien-he', 'is_active' => true],
        ],
    ]);

    $this->get(route('bio.show'))
        ->assertOk()
        ->assertSee('Liên kết cũ')
        ->assertSee('class="bx bx-link-alt"', false);
});

test('admin bio page is registered as a separate navigation page', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/settings/bio')
        ->assertOk()
        ->assertViewIs('app');
});
