<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

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

test('seo posts store focus and meta keywords separately with legacy public fallback', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = SeoCategory::query()->create([
        'name' => 'Kiến thức game',
        'slug' => 'kien-thuc-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/posts', [
            'seo_category_id' => $category->id,
            'title' => 'Cách nạp game an toàn',
            'slug' => 'cach-nap-game-an-toan',
            'content' => [],
            'robots' => 'index,follow',
            'focus_keyword' => 'nạp game an toàn',
            'meta_keywords' => 'nạp game an toàn, nạp game teamobi, nạp carot',
            'status' => 'published',
        ])
        ->assertCreated()
        ->assertJsonPath('data.focus_keyword', 'nạp game an toàn')
        ->assertJsonPath('data.meta_keywords', 'nạp game an toàn, nạp game teamobi, nạp carot');

    $post = SeoPost::query()->findOrFail($response->json('data.id'));

    expect($post->focus_keyword)->toBe('nạp game an toàn')
        ->and($post->meta_keywords)->toBe('nạp game an toàn, nạp game teamobi, nạp carot');

    $publicUrl = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);
    $this->get($publicUrl)
        ->assertOk()
        ->assertSee('<meta name="keywords" content="nạp game an toàn, nạp game teamobi, nạp carot">', false);

    $post->update(['meta_keywords' => null]);

    $this->get($publicUrl)
        ->assertOk()
        ->assertSee('<meta name="keywords" content="nạp game an toàn">', false);
});

test('seo post meta keywords are limited to one thousand characters', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/posts', [
            'title' => 'Bài viết keyword dài',
            'slug' => 'bai-viet-keyword-dai',
            'content' => [],
            'robots' => 'index,follow',
            'meta_keywords' => str_repeat('a', 1001),
            'status' => 'draft',
        ])
        ->assertUnprocessable();
});

test('admin can update seo content and the public page renders the saved body', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = SeoCategory::query()->create([
        'name' => 'HÆ°á»›ng dáº«n náº¡p game',
        'slug' => 'huong-dan-nap-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'BÃ i hÆ°á»›ng dáº«n cáº§n cáº­p nháº­t',
        'slug' => 'bai-huong-dan-can-cap-nhat',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'draft',
    ]);
    $content = [
        ['type' => 'paragraph', 'children' => [['text' => 'Ná»™i dung vá»«a nháº­p pháº£i Ä‘Æ°á»£c lÆ°u ngay.']]],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/seo/posts/'.$post->id, [
            'seo_category_id' => $category->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $content,
            'robots' => 'index,follow',
            'status' => 'published',
            'published_at' => now()->toISOString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.content.0.children.0.text', 'Ná»™i dung vá»«a nháº­p pháº£i Ä‘Æ°á»£c lÆ°u ngay.');

    expect($post->fresh()->content)->toBe($content);

    $this->get(route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]))
        ->assertOk()
        ->assertSee('Ná»™i dung vá»«a nháº­p pháº£i Ä‘Æ°á»£c lÆ°u ngay.');
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
    $category = SeoCategory::query()->create([
        'name' => 'Hướng dẫn game',
        'slug' => 'huong-dan-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
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

    $this->get(route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]))
        ->assertOk()
        ->assertSee('Hướng dẫn game')
        ->assertSee('href="'.route('seo.category', $category->slug).'"', false)
        ->assertSee('src="/storage/uploads/image/dedicated-cover.webp"', false)
        ->assertSee('alt="Ảnh đại diện riêng của bài SEO"', false)
        ->assertSee('<script type="application/ld+json">', false)
        ->assertSee('<meta property="og:image" content="'.url('/storage/uploads/image/dedicated-cover.webp').'">', false);
});

test('categorized seo posts use category urls and render seo metadata', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Mẹo nạp game',
        'slug' => 'meo-nap-game',
        'seo_title' => 'Mẹo nạp game an toàn',
        'seo_description' => 'Tổng hợp hướng dẫn nạp game an toàn.',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Kiểm tra giao dịch nạp game',
        'slug' => 'kiem-tra-giao-dich-nap-game',
        'content' => [['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Các bước kiểm tra']]]],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $newUrl = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);

    $this->get($newUrl)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$newUrl.'">', false)
        ->assertSee('Các bước kiểm tra')
        ->assertSee('BreadcrumbList');
});

test('legacy seo post urls redirect permanently to the categorized url', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Mẹo nạp game',
        'slug' => 'meo-nap-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Kiểm tra giao dịch nạp game',
        'slug' => 'kiem-tra-giao-dich-nap-game',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $newUrl = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);

    $this->get('/tin-tuc/'.$post->slug)
        ->assertRedirect($newUrl)
        ->assertStatus(301);

    $this->get('/bai-viet/'.$post->slug)
        ->assertRedirect($newUrl)
        ->assertStatus(301);
});

test('seo category page lists its posts with the categorized url', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Mẹo nạp game',
        'slug' => 'meo-nap-game',
        'seo_title' => 'Mẹo nạp game an toàn',
        'seo_description' => 'Tổng hợp hướng dẫn nạp game an toàn.',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Kiểm tra giao dịch nạp game',
        'slug' => 'kiem-tra-giao-dich-nap-game',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $newUrl = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);

    $this->get(route('seo.category', $category->slug))
        ->assertOk()
        ->assertViewIs('pages.seo.category')
        ->assertViewHas('posts', fn (LengthAwarePaginator $posts): bool => $posts->total() === 1)
        ->assertSee('Mẹo nạp game an toàn')
        ->assertSee('href="'.$newUrl.'"', false);
});

test('seo category page paginates and filters only published posts from its category', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Hướng dẫn chuyên sâu',
        'slug' => 'huong-dan-chuyen-sau',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $otherCategory = SeoCategory::query()->create([
        'name' => 'Danh mục khác',
        'slug' => 'danh-muc-khac',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);

    foreach (range(1, 13) as $index) {
        SeoPost::query()->create([
            'seo_category_id' => $category->id,
            'title' => $index === 1 ? 'Hướng dẫn tìm kiếm đặc biệt' : "Bài hướng dẫn {$index}",
            'slug' => "bai-huong-dan-{$index}",
            'content' => [],
            'robots' => 'index,follow',
            'status' => 'published',
            'published_at' => now()->subMinutes($index),
        ]);
    }

    SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Bản nháp không công khai',
        'slug' => 'ban-nhap-khong-cong-khai',
        'content' => [],
        'robots' => 'noindex,nofollow',
        'status' => 'draft',
    ]);
    SeoPost::query()->create([
        'seo_category_id' => $otherCategory->id,
        'title' => 'Bài thuộc danh mục khác',
        'slug' => 'bai-thuoc-danh-muc-khac',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get(route('seo.category', $category->slug))
        ->assertOk()
        ->assertViewHas('posts', fn (LengthAwarePaginator $posts): bool => $posts->total() === 13 && $posts->count() === 12)
        ->assertDontSee('Bản nháp không công khai')
        ->assertDontSee('Bài thuộc danh mục khác');

    $this->get(route('seo.category', [$category->slug, 'page' => 2]))
        ->assertOk()
        ->assertViewHas('posts', fn (LengthAwarePaginator $posts): bool => $posts->currentPage() === 2 && $posts->count() === 1)
        ->assertSee('<link rel="canonical" href="'.route('seo.category', $category->slug).'?page=2">', false);

    $this->get(route('seo.category', [$category->slug, 'q' => 'đặc biệt']))
        ->assertOk()
        ->assertViewHas('posts', fn (LengthAwarePaginator $posts): bool => $posts->total() === 1)
        ->assertSee('Hướng dẫn tìm kiếm đặc biệt')
        ->assertSee('<meta name="robots" content="noindex,follow">', false);

    $this->get(route('sitemap.categories'))
        ->assertOk()
        ->assertSee(route('seo.category', $category->slug));
});

test('seo post url rejects a mismatched or inactive category', function (): void {
    $activeCategory = SeoCategory::query()->create([
        'name' => 'Danh mục đúng',
        'slug' => 'danh-muc-dung',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $inactiveCategory = SeoCategory::query()->create([
        'name' => 'Danh mục ẩn',
        'slug' => 'danh-muc-an',
        'robots' => 'index,follow',
        'is_active' => false,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $activeCategory->id,
        'title' => 'Bài viết đúng danh mục',
        'slug' => 'bai-viet-dung-danh-muc',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get(route('seo.show', ['categorySlug' => $inactiveCategory->slug, 'postSlug' => $post->slug]))
        ->assertNotFound();

    $post->update(['seo_category_id' => $inactiveCategory->id]);

    $this->get(route('seo.show', ['categorySlug' => $inactiveCategory->slug, 'postSlug' => $post->slug]))
        ->assertNotFound();
    $this->get('/tin-tuc/'.$post->slug)->assertNotFound();
});

test('admin rejects reserved category slugs and requires a category for published posts', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/categories', [
            'name' => 'Tài khoản',
            'slug' => 'tai-khoan',
            'robots' => 'index,follow',
            'is_active' => true,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Slug danh mục này trùng với đường dẫn hệ thống.');

    $this->actingAs($admin)
        ->postJson('/api/admin-api/seo/posts', [
            'title' => 'Bài viết thiếu danh mục',
            'slug' => 'bai-viet-thieu-danh-muc',
            'content' => [],
            'robots' => 'index,follow',
            'status' => 'published',
            'published_at' => now()->toISOString(),
        ])
        ->assertUnprocessable();
});

test('scheduled seo posts require a future schedule time', function (?string $scheduledAt, bool $valid): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = SeoCategory::query()->create([
        'name' => 'Tin game '.uniqid(),
        'slug' => 'tin-game-'.uniqid(),
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $response = $this->actingAs($admin)->postJson('/api/admin-api/seo/posts', [
        'seo_category_id' => $category->id,
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
