<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;

function articleWebsitePost(array $attributes = []): SeoPost
{
    $category = SeoCategory::query()->firstOrCreate(['slug' => 'huong-dan-nro'], ['name' => 'Hướng dẫn NRO', 'is_active' => true]);

    return SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Kinh nghiệm chơi Ngọc Rồng Online',
        'slug' => 'kinh-nghiem-nro-'.fake()->unique()->numerify('#####'),
        'type' => 'knowledge', 'status' => 'published', 'published_at' => now()->subDay(),
        'robots' => 'index,follow', 'content' => [], 'article_schema' => true, 'breadcrumb_schema' => true,
        ...$attributes,
    ]);
}

test('home is a Blade tool hub and news has a separate searchable page', function (): void {
    $post = articleWebsitePost();
    articleWebsitePost(['title' => 'Bài nháp ẩn', 'status' => 'draft']);
    articleWebsitePost(['title' => 'Bảng giá ẩn', 'type' => 'price']);
    articleWebsitePost(['title' => 'Bài tương lai ẩn', 'published_at' => now()->addDay()]);
    $this->get('/')->assertOk()->assertViewIs('client.home.index')->assertSee('data-home-tool-grid', false)
        ->assertSee($post->title)->assertDontSee('Bài nháp ẩn')->assertDontSee('Bảng giá ẩn')->assertDontSee('Bài tương lai ẩn')
        ->assertDontSee('/nap-tien')->assertDontSee('/dashboard')->assertDontSee('/cong-tac-vien');
    $this->get('/tin-tuc?q=khong-tim-thay')->assertOk()->assertDontSee($post->title)->assertSee('noindex,follow');
});

test('mobile bottom navigation balances its four items in four equal columns', function (bool $signedIn): void {
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $html = $this->get(route('home'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $grid = $xpath->query('//nav[@data-mobile-bottom-nav]/div')->item(0);

    expect($grid)->not->toBeNull()
        ->and($grid->getAttribute('class'))->toContain('grid-cols-4')->not->toContain('grid-cols-5')
        ->and($xpath->query('./a', $grid))->toHaveCount(4);
})->with(['guest' => false, 'member' => true]);

test('all published articles are reachable with pagination and page canonicals', function (): void {
    foreach (range(1, 13) as $number) {
        articleWebsitePost(['slug' => 'bai-viet-'.$number]);
    }
    $this->get('/tin-tuc?page=2')->assertOk()->assertViewHas('posts', fn ($posts): bool => $posts->total() === 13 && $posts->count() === 1)
        ->assertSee('href="'.route('seo.index').'?page=2"', false);
});

test('article pages retain canonical redirects and Article schema', function (): void {
    $post = articleWebsitePost();
    $url = route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug]);
    $this->get($url)->assertOk()->assertSee('"@type":"Article"', false)->assertSee('rel="canonical" href="'.$url.'"', false);
    $this->get('/bai-viet/'.$post->slug)->assertRedirect($url)->assertStatus(301);
});

test('blog articles place the shared absolute TOC inside the full article instead of the sidebar', function (): void {
    $post = articleWebsitePost(['content' => [
        ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Các bước thực hiện']]],
        ['type' => 'paragraph', 'children' => [['text' => 'Nội dung hướng dẫn chi tiết.']]],
    ]]);
    $html = $this->get(route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug]))
        ->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@id="blog-article"][@data-seo-article]'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="blog-article"]//*[@data-seo-toc-toggle][contains(@class,"absolute")]'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="blog-article"]//*[@data-seo-toc][@hidden]'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="blog-article"]//*[@data-seo-content]//h2'))->toHaveCount(1)
        ->and($xpath->query('//*[@id="blog-article"]//*[@data-seo-expand]'))->toHaveCount(0)
        ->and($xpath->query('//aside//nav[@aria-label="Mục lục bài viết"]'))->toHaveCount(0);
});

test('article and category breadcrumbs emit one valid ordered JSON LD trail', function (): void {
    $post = articleWebsitePost();
    $postUrl = route('seo.show', ['categorySlug' => $post->category->slug, 'postSlug' => $post->slug]);
    $categoryUrl = route('seo.category', $post->category->slug);
    foreach ([$postUrl => 4, $categoryUrl => 3, route('seo.index') => 2] as $url => $length) {
        $html = $this->get($url)->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($document);
        $trails = [];
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            $schema = json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
            if (($schema['@type'] ?? '') === 'BreadcrumbList') {
                $trails[] = $schema['itemListElement'];
            }
        }
        expect($trails)->toHaveCount(1)
            ->and($trails[0])->toHaveCount($length)
            ->and(array_column($trails[0], 'position'))->toBe(range(1, $length))
            ->and($trails[0][0]['item'])->toBe(route('home'))
            ->and($trails[0][$length - 1]['item'])->toBe($url);
        foreach ($trails[0] as $item) {
            expect($item['@type'])->toBe('ListItem')
                ->and($item['name'])->not->toBeEmpty()
                ->and(filter_var($item['item'], FILTER_VALIDATE_URL))->not->toBeFalse();
        }
    }
});

test('legacy commercial endpoints are not available', function (string $method, string $uri): void {
    $admin = User::factory()->create(['role' => 'admin']);
    expect($this->actingAs($admin)->json($method, $uri)->status())->toBeIn([404, 405]);
})->with([
    ['GET', '/dashboard/don-hang'], ['GET', '/cong-tac-vien'],
    ['GET', '/bang-gia'], ['GET', '/nap-game-teamobi'], ['GET', '/tai-khoan/so-du'], ['GET', '/tai-khoan/dong-tien'],
    ['GET', '/tai-khoan/api-key'], ['POST', '/api/orders'], ['POST', '/api/recharge/webhook'],
    ['GET', '/api/admin-api/topup/orders'], ['POST', '/api/admin-api/users/1/wallet-adjust'],
    ['GET', '/api/admin-api/affiliate'], ['GET', '/api/admin-api/seo/games'],
]);

test('new users and authentication payloads no longer create or expose wallets', function (): void {
    $user = User::factory()->create();
    expect(method_exists($user, 'wallets'))->toBeFalse();
    $this->actingAs($user)->getJson('/api/user')->assertOk()->assertJsonMissingPath('wallet');
    $this->get('/tai-khoan')->assertOk()->assertDontSee('/dong-tien')->assertDontSee('/api-key');
});

test('sitemaps contain articles and categories without sales pages', function (): void {
    $post = articleWebsitePost();
    $this->get('/sitemap.xml')->assertOk()->assertSee('sitemap-articles.xml')->assertDontSee('sitemap-games.xml');
    $this->get('/sitemap-pages.xml')->assertOk()->assertDontSee('nap-game')->assertDontSee('bang-gia')->assertDontSee('thanh-toan');
    $this->get('/sitemap-articles.xml')->assertOk()->assertSee($post->slug);
});

test('admin Vue and article management remain protected and functional', function (): void {
    $this->get('/admin')->assertRedirect();
    $member = User::factory()->create();
    $this->actingAs($member)->get('/admin')->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get('/admin')->assertOk()->assertViewIs('app');
    $category = SeoCategory::query()->create(['name' => 'Tiện ích', 'slug' => 'tien-ich', 'is_active' => true]);
    $this->postJson('/api/admin-api/seo/posts', [
        'title' => 'Tiện ích NRO', 'slug' => 'tien-ich-nro', 'type' => 'guide', 'seo_category_id' => $category->id,
        'status' => 'published', 'robots' => 'index,follow', 'content' => [],
    ])->assertCreated();
    $this->postJson('/api/admin-api/seo/posts', [
        'title' => 'Bảng giá', 'slug' => 'bang-gia', 'type' => 'price', 'status' => 'draft', 'robots' => 'index,follow', 'content' => [],
    ])->assertUnprocessable();
    $this->getJson('/api/admin-api/seo/post-options')->assertOk()->assertJsonPath('data.services', []);
    $this->getJson('/api/admin-api/seo/sitemaps')->assertOk();
    $this->getJson('/api/admin-api/users')->assertOk()->assertJsonMissingPath('data.stats.total_user_wallet_balance');
    $this->getJson('/api/admin-api/users/'.$member->id)->assertOk()->assertJsonMissingPath('data.wallet');
});

test('article database bootstrap preserves existing tables and account data', function (): void {
    $user = User::factory()->create();
    $migration = require database_path('migrations/2026_10_06_093807_prepare_article_website_tables.php');
    $migration->up();
    expect(Schema::hasTable('seo_posts'))->toBeTrue()->and(Schema::hasTable('settings'))->toBeTrue();
    $this->assertModelExists($user);
});

test('article bootstrap creates missing editorial tables without a commerce catalog', function (): void {
    Schema::drop('seo_posts');
    Schema::drop('seo_redirects');
    Schema::drop('settings');
    $migration = require database_path('migrations/2026_10_06_093807_prepare_article_website_tables.php');
    $migration->up();
    $migration->up();
    (require database_path('migrations/2026_10_24_102752_add_language_and_lock_version_to_seo_posts_table.php'))->up();
    expect(Schema::hasColumns('seo_posts', ['type', 'faq', 'cover_image', 'meta_keywords']))->toBeTrue();
    $post = articleWebsitePost();
    $this->get('/')->assertOk()->assertSee($post->title);
});

test('default seeding initializes editorial categories without commercial products or test users', function (): void {
    $users = User::query()->count();
    expect(Schema::hasTable('games'))->toBeFalse();
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect(SeoCategory::query()->count())->toBe(4)
        ->and(User::query()->count())->toBe($users)
        ->and(Schema::hasTable('games'))->toBeFalse();
    $this->get('/')->assertOk()->assertSee('Tiện ích NRO')->assertSee('Công cụ hỗ trợ');
    $this->get('/tin-tuc')->assertOk()->assertSee('Hướng dẫn Ngọc Rồng');
});

test('home promotes tools before a limited news preview with the original mobile shell', function (): void {
    foreach (range(1, 5) as $number) {
        articleWebsitePost(['slug' => 'preview-'.$number]);
    }
    $response = $this->get('/')->assertOk()->assertViewHas('latestPosts', fn ($posts): bool => $posts->count() === 3)
        ->assertSee('data-home-compact-header', false)->assertSee('data-mobile-bottom-nav', false)
        ->assertDontSee('client-page-scale', false)->assertSee(route('seo.index'))
        ->assertDontSee('aria-label="Chủ đề tin tức"', false);
    expect(strpos($response->getContent(), 'data-home-tool-grid'))->toBeLessThan(strpos($response->getContent(), 'data-home-latest-news'));
    $this->get('/tin-tuc')->assertOk()->assertViewIs('pages.seo.index')->assertViewHas('posts', fn ($posts): bool => $posts->total() === 5);
});

test('old homepage search links redirect to the dedicated news section', function (): void {
    $this->get('/?q=broly&page=2')->assertRedirect(route('seo.index', ['q' => 'broly', 'page' => 2]));
});

test('news never renders the homepage SEO content or FAQ', function (): void {
    Setting::query()->create(['key' => 'home_seo_article_title', 'value' => 'Home-only SEO heading', 'type' => 'string']);
    Setting::query()->create(['key' => 'home_seo_is_published', 'value' => true, 'type' => 'boolean']);
    Setting::query()->create(['key' => 'home_seo_content', 'value' => json_encode([['type' => 'paragraph', 'children' => [['text' => 'Only for tool hub']]]], JSON_THROW_ON_ERROR), 'type' => 'json']);
    $this->get('/')->assertOk()->assertSee('Home-only SEO heading')->assertSee('Only for tool hub');
    $this->get('/tin-tuc')->assertOk()->assertDontSee('Home-only SEO heading')->assertDontSee('Only for tool hub');
});

test('a new tool can be registered without changing homepage or navigation templates', function (): void {
    config()->set('tools.items', [[
        'name' => 'Công cụ bổ sung', 'description' => 'Công cụ đã đăng ký', 'icon' => 'bx-joystick',
        'route' => 'nro.notifies.page', 'parameters' => ['state' => 'history'],
    ]]);
    $this->get('/')->assertOk()->assertSee('Công cụ bổ sung')->assertSee(route('nro.notifies.page', ['state' => 'history']));
});

test('home lists NRO tools without the retired topup link', function (): void {
    $response = $this->get('/')->assertOk()
        ->assertViewHas('tools', fn ($tools): bool => $tools->pluck('name')->all() === [
            'Ghép ảnh game', 'Thông báo game', 'Đổi mật khẩu game', 'Xác minh tài khoản', 'Tải mod',
        ]);

    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $toolGrid = $xpath->query('//*[@data-home-tool-grid]')->item(0);
    $links = $xpath->query('.//a', $toolGrid);
    $buttons = $xpath->query('.//button', $toolGrid);

    expect($links->length)->toBe(3)
        ->and($links->item(0)->getAttribute('href'))->toBe(route('tools.game-image'))
        ->and($links->item(1)->getAttribute('href'))->toBe(route('nro.notifies.page'))
        ->and($links->item(2)->getAttribute('href'))->toBe(route('tools.game-password'))
        ->and($buttons->length)->toBe(2)
        ->and($buttons->item(1)->textContent)->toContain('Tải mod');

    foreach ($buttons as $button) {
        $icon = $xpath->query('./span', $button)->item(0);
        $badge = $xpath->query('./span/span', $button)->item(0);
        expect($button->hasAttribute('disabled'))->toBeTrue()
            ->and($button->hasAttribute('href'))->toBeFalse()
            ->and($button->textContent)->toContain('Sắp ra mắt')
            ->and($xpath->query('./span', $button)->length)->toBe(1)
            ->and($icon->getAttribute('class'))->toContain('relative')
            ->and($icon->hasAttribute('aria-hidden'))->toBeFalse()
            ->and(trim($badge->textContent))->toBe('Sắp ra mắt')
            ->and($badge->getAttribute('class'))->toContain('absolute', 'bottom-2');
    }
});

test('tools navigation exposes the shared modal on public pages', function (string $path): void {
    $response = $this->get($path)->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $triggers = $xpath->query('//*[@data-client-tools-open]');
    $modal = $xpath->query('//dialog[@id="client-tools-modal"]')->item(0);

    expect($triggers->length)->toBe(3)
        ->and($modal)->not->toBeNull()
        ->and($modal->hasAttribute('open'))->toBeFalse()
        ->and($modal->getAttribute('aria-labelledby'))->toBe('client-tools-title');

    foreach ($triggers as $trigger) {
        expect($trigger->getAttribute('aria-controls'))->toBe('client-tools-modal')
            ->and($trigger->getAttribute('aria-haspopup'))->toBe('dialog');
    }

    $links = $xpath->query('.//*[@data-client-tools-list]//a', $modal);
    foreach ($xpath->query('.//*[@data-client-tools-list]//button[@disabled]', $modal) as $button) {
        $badge = $xpath->query('./span/span', $button)->item(0);
        expect($xpath->query('./span', $button)->length)->toBe(1)
            ->and(trim($badge->textContent))->toBe('Sắp ra mắt')
            ->and($badge->getAttribute('class'))->toContain('absolute', 'bottom-2');
    }
    expect($links->length)->toBe(3)
        ->and($links->item(0)->getAttribute('href'))->toBe(route('tools.game-image'))
        ->and($links->item(2)->getAttribute('href'))->toBe(route('tools.game-password'))
        ->and($xpath->query('.//*[@data-client-tools-list]//button[@disabled]', $modal)->length)->toBe(2)
        ->and($xpath->query('.//*[@data-client-tools-list]//button[@disabled]', $modal)->item(1)->textContent)->toContain('Tải mod', 'Sắp ra mắt')
        ->and($xpath->query('.//button[@data-client-tools-close]', $modal)->length)->toBe(1);
})->with(['/', '/tin-tuc', '/thong-bao-game']);
