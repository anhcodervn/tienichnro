<?php

use App\Models\Game;
use App\Models\GameSeoSetting;
use App\Models\User;
use App\Support\SettingStore;
use App\Support\SitemapUrlService;

test('only platform admins can manage game seo', function (): void {
    $game = Game::factory()->create();

    $this->getJson('/api/admin-api/seo/games')->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->patchJson("/api/admin-api/seo/games/{$game->id}", [])
        ->assertForbidden();
});

test('admin can manage complete seo for one game without changing its catalog data', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online', 'slug' => 'ngoc-rong-online']);
    app(SettingStore::class)->putMany(['og_image' => '/uploads/branding/home-og.webp']);

    $this->actingAs($admin)->getJson('/api/admin-api/seo/games')
        ->assertOk()
        ->assertJsonPath('data.games.0.id', $game->id)
        ->assertJsonPath('data.games.0.fallback_og_image', '/uploads/branding/home-og.webp')
        ->assertJsonPath('data.games.0.public_url', route('topup.game', ['game' => $game]));

    $content = [
        ['type' => 'heading', 'level' => 2, 'children' => [['text' => 'Cách nạp an toàn']]],
        ['type' => 'paragraph', 'children' => [['text' => '<script>alert("xss")</script>']]],
    ];

    $this->actingAs($admin)->patchJson("/api/admin-api/seo/games/{$game->id}", [
        'meta_title' => 'Nạp Ngọc Rồng Online nhanh chóng, giá tốt',
        'meta_description' => 'Nạp Ngọc Rồng Online tự động, hiển thị rõ giá nhận và lịch sử giao dịch.',
        'meta_keywords' => 'nạp ngọc rồng, nạp nro, giá ngọc rồng',
        'h1' => 'Nạp game Ngọc Rồng Online',
        'article_title' => 'Hướng dẫn nạp Ngọc Rồng Online',
        'content' => $content,
        'og_image' => '/uploads/seo/ngoc-rong-og.webp',
        'og_image_alt' => 'Nạp Ngọc Rồng Online',
        'canonical_url' => route('topup.game', ['game' => $game]),
        'robots' => 'index,follow',
        'faqs' => [['question' => 'Nạp bao lâu?', 'answer' => 'Hệ thống xử lý tự động.']],
        'is_published' => true,
        'breadcrumb_schema' => true,
        'webpage_schema' => true,
    ])->assertOk()->assertJsonPath('data.meta_keywords', 'nạp ngọc rồng, nạp nro, giá ngọc rồng');

    $setting = GameSeoSetting::query()->whereBelongsTo($game)->firstOrFail();
    expect($game->fresh()->slug)->toBe('ngoc-rong-online')
        ->and($setting->meta_title)->toBe('Nạp Ngọc Rồng Online nhanh chóng, giá tốt')
        ->and($setting->content)->toBe($content);

    $this->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertSee('<title>Nạp Ngọc Rồng Online nhanh chóng, giá tốt</title>', false)
        ->assertSee('name="keywords" content="nạp ngọc rồng, nạp nro, giá ngọc rồng"', false)
        ->assertSee('property="og:image" content="'.url('/uploads/seo/ngoc-rong-og.webp').'"', false)
        ->assertSee('property="og:image:alt" content="Nạp Ngọc Rồng Online"', false)
        ->assertSee('Nạp game Ngọc Rồng Online')
        ->assertSee('Hướng dẫn nạp Ngọc Rồng Online')
        ->assertSee('data-seo-collapsible data-expanded="false"', false)
        ->assertSee('id="game-seo-content"', false)
        ->assertSee('aria-controls="game-seo-content"', false)
        ->assertSee('data-seo-collapsible-toggle', false)
        ->assertSee('Xem thêm')
        ->assertSee('Nạp bao lâu?')
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("xss")</script>', false)
        ->assertSee('FAQPage');
});

test('game seo validates metadata URLs and prevents a second h1', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();

    $this->actingAs($admin)->patchJson("/api/admin-api/seo/games/{$game->id}", [
        'meta_title' => str_repeat('a', 256),
        'canonical_url' => 'javascript:alert(1)',
        'og_image' => 'javascript:alert(1)',
        'content' => [['type' => 'heading', 'level' => 1, 'children' => [['text' => 'Sai H1']]]],
    ])->assertUnprocessable();

    expect(GameSeoSetting::query()->whereBelongsTo($game)->exists())->toBeFalse();
});

test('partial game seo update preserves fields that were not submitted', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    GameSeoSetting::factory()->for($game)->create([
        'meta_title' => 'Tiêu đề cũ',
        'meta_description' => 'Mô tả cũ',
        'meta_keywords' => 'keyword cũ',
    ]);

    $this->actingAs($admin)->patchJson("/api/admin-api/seo/games/{$game->id}", [
        'meta_title' => 'Tiêu đề mới',
    ])->assertOk();

    $setting = $game->seoSetting()->firstOrFail();
    expect($setting->meta_title)->toBe('Tiêu đề mới')
        ->and($setting->meta_description)->toBe('Mô tả cũ')
        ->and($setting->meta_keywords)->toBe('keyword cũ');
});

test('game landing falls back to the home open graph image', function (): void {
    $game = Game::factory()->create(['slug' => 'game-og-fallback']);
    GameSeoSetting::factory()->for($game)->create(['og_image' => null]);
    app(SettingStore::class)->putMany(['og_image' => '/uploads/branding/home-og.webp']);

    $this->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertSee('property="og:image" content="'.url('/uploads/branding/home-og.webp').'"', false);
});

test('published noindex and external canonical game pages are excluded from sitemap', function (): void {
    $indexedGame = Game::factory()->create(['status' => 'active']);
    $noindexGame = Game::factory()->create(['status' => 'active']);
    $externalCanonicalGame = Game::factory()->create(['status' => 'active']);
    GameSeoSetting::factory()->for($noindexGame)->create(['robots' => 'noindex,follow']);
    GameSeoSetting::factory()->for($externalCanonicalGame)->create([
        'canonical_url' => 'https://example.com/trang-thay-the',
    ]);

    $urls = app(SitemapUrlService::class)->gameUrls()->pluck('loc');

    expect($urls)
        ->toContain(route('topup.game', ['game' => $indexedGame]))
        ->not->toContain(route('topup.game', ['game' => $noindexGame]))
        ->not->toContain(route('topup.game', ['game' => $externalCanonicalGame]));
});

test('game catalog updates cannot overwrite separate seo settings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['slug' => 'game-seo-isolated']);
    GameSeoSetting::factory()->for($game)->create([
        'meta_title' => 'SEO title được quản lý riêng',
        'meta_description' => 'SEO description được quản lý riêng.',
    ]);

    $this->actingAs($admin)->putJson("/api/admin-api/games/{$game->id}", [
        'name' => 'Tên game đã cập nhật',
        'slug' => 'game-seo-isolated',
        'short_name' => 'GAME',
        'reward_label' => 'Vật phẩm',
        'provider_service_code' => null,
        'package_mode' => 'custom',
        'image' => null,
        'description' => 'Mô tả vận hành mới.',
        'status' => 'active',
        'sort_order' => 1,
        'metadata' => [],
        'checkout_fields' => [[
            'key' => 'game_account', 'label' => 'Tài khoản game', 'placeholder' => '',
            'required' => true, 'regex' => '',
        ]],
        'meta_title' => 'Không được ghi đè',
    ])->assertOk()->assertJsonMissingPath('meta_title');

    expect($game->fresh()->name)->toBe('Tên game đã cập nhật')
        ->and($game->seoSetting()->firstOrFail()->meta_title)->toBe('SEO title được quản lý riêng');
});
