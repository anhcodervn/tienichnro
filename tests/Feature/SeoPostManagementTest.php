<?php

use App\Models\SeoPost;
use App\Models\User;

test('only admins can manage seo posts', function (): void {
    $this->postJson('/api/admin-api/seo/posts', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/admin-api/seo/posts', [])
        ->assertForbidden();
});

test('admin can save a dedicated cover image and custom canonical for an seo post', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/posts', [
            'title' => 'Hướng dẫn nạp Ngọc Rồng Online',
            'slug' => 'huong-dan-nap-ngoc-rong-online',
            'excerpt' => 'Hướng dẫn nạp game nhanh chóng.',
            'content' => [],
            'cover_image' => '/storage/uploads/image/2026/08/23/nap-ngoc-rong.webp',
            'cover_alt' => 'Giao diện nạp Ngọc Rồng Online',
            'seo_title' => 'Nạp Ngọc Rồng Online nhanh và an toàn',
            'seo_description' => 'Hướng dẫn nạp Ngọc Rồng Online rõ giá, nhanh chóng và dễ tra cứu.',
            'canonical_url' => 'https://napcarot.com/tin-tuc/nap-ngoc-rong-online',
            'robots' => 'index,follow',
            'status' => 'draft',
        ])
        ->assertCreated()
        ->assertJsonPath('data.cover_image', '/storage/uploads/image/2026/08/23/nap-ngoc-rong.webp')
        ->assertJsonPath('data.cover_alt', 'Giao diện nạp Ngọc Rồng Online')
        ->assertJsonPath('data.canonical_url', 'https://napcarot.com/tin-tuc/nap-ngoc-rong-online');

    $post = SeoPost::query()->firstOrFail();

    expect($post->cover_image)->toBe('/storage/uploads/image/2026/08/23/nap-ngoc-rong.webp')
        ->and($post->canonical_url)->toBe('https://napcarot.com/tin-tuc/nap-ngoc-rong-online');
});

test('seo post cover image and canonical values are validated', function (array $overrides): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = [
        'title' => 'Bài viết SEO',
        'slug' => 'bai-viet-seo',
        'content' => [],
        'cover_image' => '/storage/uploads/image/cover.webp',
        'cover_alt' => 'Ảnh minh họa bài viết',
        'canonical_url' => 'https://napcarot.com/tin-tuc/bai-viet-seo',
        'robots' => 'index,follow',
        'status' => 'draft',
        ...$overrides,
    ];

    $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/posts', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $message): bool => $message !== '');
})->with([
    'missing cover alt' => [['cover_alt' => '']],
    'unsafe cover image' => [['cover_image' => 'javascript:alert(1)']],
    'relative canonical' => [['canonical_url' => '/tin-tuc/bai-viet-seo']],
    'unsafe canonical' => [['canonical_url' => 'javascript:alert(1)']],
    'base64 editor image' => [['content' => [['type' => 'image', 'src' => 'data:image/png;base64,AAAA']]]],
    'blob editor image' => [['content' => [['type' => 'image', 'src' => 'blob:https://napcarot.com/example']]]],
]);

test('public seo page prefers the dedicated cover image over the first editor image', function (): void {
    $post = SeoPost::query()->create([
        'title' => 'Bài SEO có ảnh đại diện',
        'slug' => 'bai-seo-co-anh-dai-dien',
        'content' => [
            ['type' => 'image', 'src' => '/storage/uploads/image/editor-image.webp', 'alt' => 'Ảnh nội dung'],
        ],
        'cover_image' => '/storage/uploads/image/dedicated-cover.webp',
        'cover_alt' => 'Ảnh đại diện riêng của bài SEO',
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get(route('seo.show', $post->slug))
        ->assertOk()
        ->assertSee('src="/storage/uploads/image/dedicated-cover.webp"', false)
        ->assertSee('alt="Ảnh đại diện riêng của bài SEO"', false)
        ->assertSee('<meta property="og:image" content="'.url('/storage/uploads/image/dedicated-cover.webp').'">', false);
});

test('scheduled seo posts require a future schedule time', function (?string $scheduledAt, bool $valid): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->postJson('/api/admin-api/seo/posts', [
        'title' => 'Bài SEO hẹn lịch',
        'slug' => 'bai-seo-hen-lich-'.($valid ? 'hop-le' : 'khong-hop-le'),
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'scheduled',
        'scheduled_at' => $scheduledAt,
    ]);

    if ($valid) {
        $response->assertCreated();

        return;
    }

    $response->assertUnprocessable();
})->with([
    'missing date' => [null, false],
    'past date' => [now()->subHour()->toISOString(), false],
    'future date' => [now()->addDay()->toISOString(), true],
]);
