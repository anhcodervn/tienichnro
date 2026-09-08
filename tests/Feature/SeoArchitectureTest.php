<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\SeoRedirect;
use App\Models\Setting;
use Database\Seeders\SeoContentSeeder;

test('homepage targets carot and exposes visible seo sections with matching schema', function (): void {
    $homeUrl = rtrim(route('home'), '/').'/';
    $response = $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>'.config('seo.homepage.title').'</title>', false)
        ->assertSee('<meta name="description" content="'.config('seo.homepage.description').'">', false)
        ->assertSee('<link rel="canonical" href="'.$homeUrl.'">', false)
        ->assertSee('Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt')
        ->assertSee('Nạp Carot cho các game Teamobi')
        ->assertSee('Tại sao nên nạp Carot tại NapCarot?')
        ->assertSee('Cách nạp Carot tại NapCarot')
        ->assertSee('Bảng giá nạp Carot')
        ->assertSee('Các câu hỏi thường gặp về nạp Carot')
        ->assertSee('Kiến thức và hướng dẫn nạp game')
        ->assertSee('FAQPage')
        ->assertSee('WebSite')
        ->assertSee('Organization')
        ->assertSeeInOrder([
            'Rõ giá',
            'Tự động',
            'Dễ tra cứu',
            'NapCarot · Nạp game Teamobi',
            'Nạp Carot Game Teamobi Nhanh Chóng, Giá Tốt',
        ]);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);

    foreach (config('seo.home_game_landings') as $landingSlug) {
        $response->assertSee(route('seo.landing', ['landingSlug' => $landingSlug]));
    }
});

test('all configured money pages are indexable and self canonical', function (): void {
    foreach (array_keys(config('seo.landings')) as $landingSlug) {
        $url = route('seo.landing', ['landingSlug' => $landingSlug]);

        $this->get($url)
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta name="robots" content="index,follow">', false)
            ->assertSee('BreadcrumbList')
            ->assertSee(config("seo.landings.{$landingSlug}.heading"));
    }
});

test('sitemap index and child sitemaps return valid xml', function (): void {
    foreach (['sitemap', 'sitemap.pages', 'sitemap.articles', 'sitemap.categories', 'sitemap.games'] as $routeName) {
        $response = $this->get(route($routeName))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        expect(simplexml_load_string($response->getContent()))->not->toBeFalse();
    }
});

test('seo content seeder preserves legacy ids creates direct redirects and is idempotent', function (): void {
    Setting::query()->updateOrCreate(['key' => 'site_name'], [
        'value' => 'NapCarot.com - nạp nhanh game TeaMobi, GoMobi Chiết Khấu Cao Tiện lợi, ổn định, giá tốt.',
        'type' => 'string',
    ]);
    $legacyCategory = SeoCategory::query()->create([
        'name' => 'Dịch vụ game ngọc rồng online',
        'slug' => 'dich-vu-game-ngoc-rong-online',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $legacyPost = SeoPost::query()->create([
        'seo_category_id' => $legacyCategory->id,
        'title' => 'Dịch vụ cũ',
        'slug' => 'dich-vu-cu',
        'content' => [['type' => 'heading', 'level' => 1, 'children' => [['text' => 'Tiêu đề cũ']]]],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $communityCategory = SeoCategory::query()->create([
        'name' => 'Giao lưu',
        'slug' => 'gia-luu',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $communityPost = SeoPost::query()->create([
        'seo_category_id' => $communityCategory->id,
        'title' => 'Cộng đồng game Ngọc Rồng Online',
        'slug' => 'cong-dong-game-ngoc-rong-online',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->seed(SeoContentSeeder::class);
    $draft = SeoPost::query()->where('slug', 'the-carot-la-gi')->firstOrFail();
    $draft->update(['content' => [['type' => 'paragraph', 'children' => [['text' => 'Admin đã sửa']]]]]);
    $this->seed(SeoContentSeeder::class);

    $newCategory = SeoCategory::query()->where('slug', 'ngoc-rong-online')->firstOrFail();
    expect($newCategory->id)->toBe($legacyCategory->id)
        ->and(SeoCategory::query()->whereIn('slug', [
            'nap-carot', 'ngoc-rong-online', 'ninja-school-online', 'avatar',
            'avatar-musik', 'hai-tac-ti-hon', 'hiep-si-online', 'huong-dan',
        ])->count())->toBe(8)
        ->and(SeoPost::query()->where('status', 'draft')->count())->toBe(17)
        ->and(SeoPost::query()->where('status', 'draft')->whereNotNull('published_at')->count())->toBe(0)
        ->and(Setting::query()->where('key', 'site_name')->value('value'))->toBe('NapCarot')
        ->and(SeoPost::query()->where('slug', 'the-carot-la-gi')->firstOrFail()->content[0]['children'][0]['text'])->toBe('Admin đã sửa')
        ->and($legacyPost->refresh()->id)->toBe($legacyPost->id)
        ->and($legacyPost->seo_category_id)->toBe($newCategory->id)
        ->and($legacyPost->content[0]['level'])->toBe(2)
        ->and(collect($legacyPost->content)->last()['children'][1]['href'])->toBe('/nap-game-ngoc-rong-online')
        ->and($communityPost->refresh()->seo_category_id)->toBe($newCategory->id)
        ->and($communityCategory->refresh()->is_active)->toBeFalse()
        ->and(SeoRedirect::query()->where('from_path', '/dich-vu-game-ngoc-rong-online/dich-vu-cu')->count())->toBe(1);

    $this->get('/dich-vu-game-ngoc-rong-online/dich-vu-cu')
        ->assertStatus(301)
        ->assertRedirect(url('/ngoc-rong-online/dich-vu-cu'));
    $this->get('/tin-tuc/dich-vu-game-ngoc-rong-online')
        ->assertStatus(301)
        ->assertRedirect(url('/tin-tuc/ngoc-rong-online'));
    $this->get('/gia-luu/cong-dong-game-ngoc-rong-online')
        ->assertStatus(301)
        ->assertRedirect(url('/ngoc-rong-online/cong-dong-game-ngoc-rong-online'));
    $this->get('/tin-tuc/gia-luu')
        ->assertStatus(301)
        ->assertRedirect(url('/tin-tuc/ngoc-rong-online'));
});

test('draft seo articles stay private until an admin publishes them', function (): void {
    $this->seed(SeoContentSeeder::class);
    $post = SeoPost::query()->where('slug', 'cach-nap-ngoc-rong-online-bang-carot')->firstOrFail();
    $category = $post->category;

    $this->get(route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]))
        ->assertNotFound();
    $this->get(route('seo.category', $category->slug))
        ->assertOk()
        ->assertDontSee($post->title);
    $this->get(route('sitemap.articles'))
        ->assertOk()
        ->assertDontSee($post->slug);

    $post->update([
        'status' => 'published',
        'robots' => 'index,follow',
        'published_at' => now(),
    ]);

    $articleUrl = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);
    $this->get($articleUrl)
        ->assertOk()
        ->assertSee('/nap-game-ngoc-rong-online');
    $this->get(route('seo.landing', ['landingSlug' => 'nap-game-ngoc-rong-online']))
        ->assertOk()
        ->assertSee($articleUrl);
    $this->get(route('sitemap.articles'))
        ->assertOk()
        ->assertSee($articleUrl);
});
