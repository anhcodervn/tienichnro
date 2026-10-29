<?php

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Admin\Setting\Services\ToolSeoService;
use App\Models\SeoPost;
use App\Models\Setting;
use App\Models\User;

function toolSeoPayload(array $overrides = []): array
{
    return array_replace([
        'title' => 'Hướng dẫn công cụ', 'excerpt' => 'Giới thiệu công cụ.',
        'content' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung hướng dẫn công cụ.']]]],
        'faq' => [['question' => 'Dùng thế nào?', 'answer' => 'Nhập chỉ số gốc.']],
        'seo_title' => 'SEO title công cụ', 'seo_description' => 'SEO description công cụ',
        'meta_keywords' => 'nro, tiềm năng', 'focus_keyword' => 'tiềm năng', 'language' => 'vi',
        'cover_image' => null, 'cover_alt' => null, 'robots' => 'index,follow',
        'article_schema' => true, 'breadcrumb_schema' => true, 'is_published' => true, 'expected_version' => 0,
    ], $overrides);
}

beforeEach(function (): void {
    app(ToolAvailabilityService::class)->create([
        'code' => 'test_potential', 'name' => 'Tính tiềm năng', 'description' => '', 'url' => '/tinh-tiem-nang',
        'is_enabled' => true, 'maintenance_message' => 'Đang bảo trì.', 'icon' => 'bx-calculator',
    ]);
});

test('tool seo detail and write require an administrator', function (): void {
    $this->getJson('/api/admin-api/settings/services/test_potential')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertUnauthorized();
    $this->actingAs(User::factory()->create(['role' => 'user']));
    $this->getJson('/api/admin-api/settings/services/test_potential')->assertForbidden();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertForbidden();
});

test('tool seo defaults and derived location are returned without creating another seo post', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $count = SeoPost::query()->count();
    $this->getJson('/api/admin-api/settings/services/test_potential')->assertOk()
        ->assertJsonPath('data.service.code', 'test_potential')->assertJsonPath('data.seo.slug', 'tinh-tiem-nang')
        ->assertJsonPath('data.seo.canonical_url', url('/tinh-tiem-nang'))->assertJsonPath('data.seo.is_published', false);
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertOk()
        ->assertJsonPath('data.lock_version', 1)->assertJsonPath('data.content.0.children.0.text', 'Nội dung hướng dẫn công cụ.');
    expect(SeoPost::query()->count())->toBe($count);
});

test('published tool seo renders below the calculator with its metadata faq and schemas', function (string $path): void {
    app(ToolAvailabilityService::class)->update('test_potential', ['url' => $path, 'is_enabled' => true, 'maintenance_message' => 'Đang bảo trì.']);
    app(ToolSeoService::class)->update('test_potential', toolSeoPayload([
        'cover_image' => '/storage/tool.webp', 'cover_alt' => 'Ảnh hướng dẫn', 'robots' => 'noindex,follow',
    ]));
    $response = $this->get($path)->assertOk()->assertSee('Nội dung hướng dẫn công cụ.')
        ->assertSee('Hướng dẫn công cụ')->assertSee('Dùng thế nào?')->assertSee('Nhập chỉ số gốc.')
        ->assertSee('<meta name="description" content="SEO description công cụ">', false)
        ->assertSee('<meta name="robots" content="noindex,follow">', false)
        ->assertSee('<link rel="canonical" href="'.url($path).'">', false)
        ->assertSee('SEO title công cụ')->assertSee('nro, tiềm năng')->assertSee('Ảnh hướng dẫn')
        ->assertSee('"@type":"Article"', false)->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"BreadcrumbList"', false);
    $html = $response->getContent();
    expect(strpos($html, '<form'))->toBeLessThan(strpos($html, 'id="tool-seo-title"'));
    expect($html)->toContain('<h1 id="tool-seo-title"')
        ->and(preg_match_all('/<h1\b/i', $html))->toBe(1);
    $toolHeader = substr($html, strpos($html, '<main'), strpos($html, '<form') - strpos($html, '<main'));
    expect($toolHeader)->toContain('<span class="mt-2 block text-3xl font-extrabold text-slate-900">')
        ->toContain('<p class="mt-3 text-sm leading-6 text-slate-600">')
        ->not->toContain('<h1');
})->with(['/tinh-tiem-nang', '/tinh-chi-so-trang-bi-nang-cap', '/tinh-tiem-nang-con-thieu']);

test('tool seo uses the configured path without query fragments or a separate article slug', function (): void {
    app(ToolAvailabilityService::class)->update('test_potential', [
        'url' => url('/tinh-tiem-nang-con-thieu/').'?source=tools#form', 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.',
    ]);
    app(ToolSeoService::class)->update('test_potential', toolSeoPayload());
    expect(app(ToolSeoService::class)->settings('test_potential')['slug'])->toBe('tinh-tiem-nang-con-thieu');
    $this->get('/tinh-tiem-nang-con-thieu?source=other')->assertOk()->assertSee('Nội dung hướng dẫn công cụ.')
        ->assertSee('<link rel="canonical" href="'.url('/tinh-tiem-nang-con-thieu').'">', false);
    $this->get('/tinh-tiem-nang')->assertOk()->assertDontSee('Nội dung hướng dẫn công cụ.');
});

test('named route tools derive their seo location without a url override', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->getJson('/api/admin-api/settings/services/game_notifications')->assertOk()
        ->assertJsonPath('data.seo.slug', 'thong-bao-game')
        ->assertJsonPath('data.seo.canonical_url', route('nro.notifies.page'));
});

test('tool seo follows a changed tool link and preserves other tools and post content', function (): void {
    app(ToolSeoService::class)->update('test_potential', toolSeoPayload());
    app(ToolSeoService::class)->update('game_notifications', toolSeoPayload(['title' => 'Bài SEO thông báo']));
    app(ToolAvailabilityService::class)->update('test_potential', [
        'url' => '/tinh-tiem-nang-con-thieu', 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.',
    ]);
    $this->get('/tinh-tiem-nang-con-thieu')->assertOk()->assertSee('Nội dung hướng dẫn công cụ.');
    $this->get('/tinh-tiem-nang')->assertOk()->assertDontSee('Nội dung hướng dẫn công cụ.');
    expect(app(ToolSeoService::class)->settings('game_notifications')['title'])->toBe('Bài SEO thông báo');
});

test('draft seo does not change the public page and can be published or unpublished', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['is_published' => false]))->assertOk();
    $this->get('/tinh-tiem-nang')->assertOk()->assertDontSee('Nội dung hướng dẫn công cụ.')->assertDontSee('SEO title công cụ');
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['expected_version' => 1]))->assertOk();
    $this->get('/tinh-tiem-nang')->assertOk()->assertSee('Nội dung hướng dẫn công cụ.');
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['is_published' => false, 'expected_version' => 2]))->assertOk();
    $this->get('/tinh-tiem-nang')->assertOk()->assertDontSee('Nội dung hướng dẫn công cụ.');
});

test('empty draft is valid and null content is normalized', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload([
        'is_published' => false, 'title' => '', 'content' => null, 'faq' => null,
    ]))->assertOk()->assertJsonPath('data.content', [])->assertJsonPath('data.faq', []);
});

test('tool seo rejects invalid content and independently supplied slug or canonical', function (array $overrides): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload($overrides))->assertUnprocessable();
})->with([
    [['slug' => 'wrong-slug']], [['canonical_url' => 'https://other.example/wrong']], [['seo_category_id' => 1]],
    [['content' => [['type' => 'image', 'src' => 'javascript:alert(1)']]]],
    [['cover_image' => 'javascript:alert(1)', 'cover_alt' => 'unsafe']], [['cover_image' => '/image.webp', 'cover_alt' => '']],
    [['title' => '']], [['content' => []]], [['robots' => 'invalid']], [['faq' => [['question' => 'Question', 'answer' => '']]]],
]);

test('external and unconfigured links can save a draft but cannot publish local tool seo', function (mixed $link): void {
    app(ToolAvailabilityService::class)->update('test_potential', ['url' => $link, 'is_enabled' => true, 'maintenance_message' => 'Bảo trì.']);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertUnprocessable();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['is_published' => false]))->assertOk()
        ->assertJsonPath('data.canonical_url', null);
})->with([null, 'https://other.example/tinh-tiem-nang']);

test('tool article escapes text faq and schema values instead of executing html', function (): void {
    $text = '<script>alert(123)</script>';
    app(ToolSeoService::class)->update('test_potential', toolSeoPayload([
        'title' => $text, 'content' => [['type' => 'paragraph', 'children' => [['text' => $text]]]],
        'faq' => [['question' => $text, 'answer' => $text]],
    ]));
    $this->get('/tinh-tiem-nang')->assertOk()->assertSee($text)->assertDontSee($text, false);
});

test('missing deleted and concurrently edited tool seo cannot be overwritten', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->getJson('/api/admin-api/settings/services/unknown')->assertNotFound();
    $this->patchJson('/api/admin-api/settings/services/unknown/seo', toolSeoPayload())->assertNotFound();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertOk();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['title' => 'Stale update']))->assertConflict();
    expect(app(ToolSeoService::class)->settings('test_potential')['title'])->toBe('Hướng dẫn công cụ');
    app(ToolAvailabilityService::class)->delete('test_potential');
    $this->getJson('/api/admin-api/settings/services/test_potential')->assertNotFound();
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertNotFound();
    $this->get('/tinh-tiem-nang')->assertOk()->assertDontSee('Nội dung hướng dẫn công cụ.');
    expect(Setting::query()->where('key', 'tool_seo.test_potential')->exists())->toBeTrue();
});
