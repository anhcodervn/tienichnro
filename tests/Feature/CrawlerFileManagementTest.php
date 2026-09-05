<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Support\SettingStore;

test('crawler files have safe defaults and conditional cache headers', function (): void {
    $response = $this->get(route('robots'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSee('User-agent: *')
        ->assertSee('Allow: /')
        ->assertSee('Sitemap: '.route('sitemap'));

    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeNull()
        ->and(public_path('robots.txt'))->not->toBeFile();

    $this->withHeader('If-None-Match', (string) $etag)
        ->get(route('robots'))
        ->assertNotModified();

    $this->get(route('ads'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertContent('');
});

test('admin can update robots and ads files without html rendering', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $robots = "User-agent: *\r\nAllow: /\r\nDisallow: /admin\r\nSitemap: ".route('sitemap');
    $ads = 'google.com, pub-1234567890123456, DIRECT, f08c47fec0942fa0';

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', [
            'robots' => 'index,follow',
            'robots_txt' => $robots,
            'ads_txt' => $ads,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.robots_txt', str_replace("\r\n", "\n", $robots)."\n")
        ->assertJsonPath('data.settings.ads_txt', $ads."\n");

    $this->get(route('robots'))
        ->assertOk()
        ->assertContent(str_replace("\r\n", "\n", $robots)."\n");

    $this->get(route('ads'))
        ->assertOk()
        ->assertContent($ads."\n");
});

test('crawler file settings reject malformed or executable content', function (array $payload, string $errorKey): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/seo', [
            'robots' => 'index,follow',
            'robots_txt' => "User-agent: *\nAllow: /\nSitemap: ".route('sitemap'),
            'ads_txt' => '',
            ...$payload,
        ])
        ->assertUnprocessable()
        ->assertJsonPath("data.errors.{$errorKey}.0", fn (string $message): bool => $message !== '');
})->with([
    'relative sitemap' => [['robots_txt' => "User-agent: *\nSitemap: /sitemap.xml"], 'robots_txt'],
    'html in robots' => [['robots_txt' => "User-agent: *\n<script>alert(1)</script>"], 'robots_txt'],
    'invalid ads relationship' => [['ads_txt' => 'google.com, pub-123, OWNER'], 'ads_txt'],
    'control byte in ads' => [['ads_txt' => "google.com, pub-\0-123, DIRECT"], 'ads_txt'],
]);

test('disabled site tells crawlers not to crawl', function (): void {
    app(SettingStore::class)->putMany(['site_active' => false]);

    $this->get(route('robots'))
        ->assertOk()
        ->assertContent("User-agent: *\nDisallow: /\n")
        ->assertDontSee('Sitemap:');
});

test('child sites do not inherit crawler advertising records from the main site', function (): void {
    Setting::query()->updateOrCreate([
        'key' => 'ads_txt',
    ], [
        'value' => 'google.com, pub-main-account, DIRECT',
        'type' => 'string',
    ]);
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->for($tenant)->create(['domain' => 'crawler-child.test']);

    $this->get('http://crawler-child.test/ads.txt')
        ->assertOk()
        ->assertContent('');

    $this->get('http://crawler-child.test/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: http://crawler-child.test/sitemap.xml')
        ->assertDontSee('napcarot.com');
});

test('sitemap only contains published indexable canonical urls', function (): void {
    $indexableCategory = SeoCategory::query()->create([
        'name' => 'Tin index',
        'slug' => 'tin-index',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $hiddenCategory = SeoCategory::query()->create([
        'name' => 'Tin noindex',
        'slug' => 'tin-noindex',
        'robots' => 'noindex,follow',
        'is_active' => true,
    ]);
    $indexablePost = SeoPost::query()->create([
        'seo_category_id' => $indexableCategory->id,
        'title' => 'Bài được index',
        'slug' => 'bai-duoc-index',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $noindexPost = SeoPost::query()->create([
        'seo_category_id' => $indexableCategory->id,
        'title' => 'Bài noindex',
        'slug' => 'bai-noindex',
        'content' => [],
        'robots' => 'noindex,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    $externalCanonicalPost = SeoPost::query()->create([
        'seo_category_id' => $indexableCategory->id,
        'title' => 'Bài canonical ngoài',
        'slug' => 'bai-canonical-ngoai',
        'content' => [],
        'canonical_url' => 'https://example.com/original',
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    SeoPost::query()->create([
        'seo_category_id' => $hiddenCategory->id,
        'title' => 'Bài trong danh mục noindex',
        'slug' => 'bai-danh-muc-noindex',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    app(SettingStore::class)->putMany(['guide_page_is_published' => false]);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('seo.category', $indexableCategory->slug))
        ->assertSee(route('seo.show', ['categorySlug' => $indexableCategory->slug, 'postSlug' => $indexablePost->slug]))
        ->assertDontSee(route('seo.category', $hiddenCategory->slug))
        ->assertDontSee($noindexPost->slug)
        ->assertDontSee($externalCanonicalPost->slug)
        ->assertDontSee(route('content.guide'))
        ->assertSee(route('content.privacy'));
});

test('document title only contains the configured site name once', function (): void {
    Setting::query()->updateOrCreate(['key' => 'site_name'], ['value' => 'NapCarot Test', 'type' => 'string']);
    $category = SeoCategory::query()->create([
        'name' => 'Tin game',
        'slug' => 'tin-game',
        'seo_title' => 'Tin game mới',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);

    $response = $this->get(route('seo.category', $category->slug))->assertOk();
    preg_match('/<title>(.*?)<\/title>/s', $response->getContent(), $matches);

    expect(html_entity_decode($matches[1] ?? ''))->toBe('Tin game mới | NapCarot Test');
});
