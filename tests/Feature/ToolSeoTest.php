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

test('password tool supports shared SEO publishing metadata TOC FAQ and hiding content after login', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $endpoint = '/api/admin-api/settings/services/game_password';
    $this->actingAs($admin)->getJson($endpoint)->assertOk()
        ->assertJsonPath('data.seo.slug', 'doi-mat-khau-game-ngoc-rong-online');
    $payload = toolSeoPayload([
        'title' => 'Hướng dẫn đổi mật khẩu Ngọc Rồng',
        'content' => [
            ['type' => 'h2', 'children' => [['text' => 'Chuẩn bị tài khoản']]],
            ['type' => 'paragraph', 'children' => [['text' => 'Bài hướng dẫn đổi mật khẩu dùng khóa captcha riêng.']]],
        ],
        'faq' => [['question' => 'Đổi lại có mất phí không?', 'answer' => 'Đơn lỗi được chạy lại miễn phí.']],
    ]);
    $this->patchJson($endpoint.'/seo', $payload)->assertOk();
    $html = $this->get(route('tools.game-password'))->assertOk()->assertSee('Bài hướng dẫn đổi mật khẩu dùng khóa captcha riêng.')
        ->assertSee('data-seo-toc-toggle', false)->assertSee('Đổi lại có mất phí không?')
        ->assertSee('"@type":"Article"', false)->assertSee('"@type":"FAQPage"', false)
        ->assertSee('SEO title công cụ')->assertSee('SEO description công cụ')->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1)
        ->and(strpos($html, 'data-password-history'))->toBeLessThan(strpos($html, 'data-tool-guide'));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-tool-guide]//*[@data-seo-faq]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-tool-guide]/following-sibling::*[@data-seo-faq]')->length)->toBe(1);
    $this->patchJson($endpoint.'/seo', [...$payload, 'hide_when_authenticated' => true, 'expected_version' => 1])->assertOk();
    $this->actingAs(User::factory()->create())->get(route('tools.game-password'))->assertOk()
        ->assertDontSee('data-tool-guide', false)->assertDontSee('"@type":"FAQPage"', false)->assertSee('SEO title công cụ');
    auth()->forgetGuards();
    $this->get(route('tools.game-password'))->assertOk()->assertSee('Bài hướng dẫn đổi mật khẩu dùng khóa captcha riêng.');
});

test('game image SEO publishes below the tool with TOC FAQ metadata and login visibility settings', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $endpoint = '/api/admin-api/settings/services/game_image';
    $this->getJson($endpoint)->assertOk()->assertJsonPath('data.seo.slug', 'ghep-anh-game');
    $payload = toolSeoPayload(['title' => 'Hướng dẫn ghép ảnh tài khoản game',
        'content' => [['type' => 'h2', 'children' => [['text' => 'Cách ghép ảnh game']]],
            ['type' => 'paragraph', 'children' => [['text' => 'Nội dung bài SEO ghép ảnh riêng.']]]],
    ]);
    $this->patchJson($endpoint.'/seo', $payload)->assertOk();
    $html = $this->get(route('tools.game-image'))->assertOk()->assertSee('Nội dung bài SEO ghép ảnh riêng.')
        ->assertSee('data-seo-toc-toggle', false)->assertSee('data-seo-faq', false)
        ->assertSee('"@type":"Article"', false)->assertSee('"@type":"FAQPage"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)->assertSee('SEO title công cụ')->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1)
        ->and(strpos($html, 'data-game-image-accounts'))->toBeLessThan(strpos($html, 'data-tool-guide'));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-tool-guide]//*[@data-seo-faq]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-tool-guide]/following-sibling::*[@data-seo-faq]')->length)->toBe(1);
    $this->patchJson($endpoint.'/seo', [...$payload, 'hide_when_authenticated' => true, 'expected_version' => 1])->assertOk();
    $this->get(route('tools.game-image'))->assertOk()->assertDontSee('data-tool-guide', false)->assertDontSee('"@type":"FAQPage"', false);
    auth()->forgetGuards();
    $this->get(route('tools.game-image'))->assertOk()->assertSee('Nội dung bài SEO ghép ảnh riêng.');
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
        ->assertJsonPath('data.seo.canonical_url', url('/tinh-tiem-nang'))->assertJsonPath('data.seo.is_published', false)
        ->assertJsonPath('data.seo.hide_when_authenticated', false);
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload())->assertOk()
        ->assertJsonPath('data.lock_version', 1)->assertJsonPath('data.content.0.children.0.text', 'Nội dung hướng dẫn công cụ.');
    expect(SeoPost::query()->count())->toBe($count);
});

test('published tool seo renders below the calculator with its metadata faq and schemas', function (string $path): void {
    $this->actingAs(User::factory()->create());
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
    expect($html)->toContain('<h2 id="tool-seo-title"')
        ->and(preg_match_all('/<h1\b/i', $html))->toBe(1);
    $toolHeader = substr($html, strpos($html, '<main'), strpos($html, '<form') - strpos($html, '<main'));
    expect($toolHeader)->toContain('id="tool-page-title"')
        ->toContain('Hướng dẫn công cụ')
        ->toContain('Giới thiệu công cụ.')
        ->and(substr_count($html, 'id="tool-seo-title"'))->toBe(1)
        ->and(substr_count($html, 'data-tool-page'))->toBe(1)
        ->and(strpos($html, 'id="tool-page-title"'))->toBeLessThan(strpos($html, 'data-tool-workspace'));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@data-tool-guide]//*[@data-seo-article]')->length)->toBe(1)
        ->and($xpath->query('//*[@data-seo-article]//*[@data-seo-faq]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-tool-guide]//*[@data-seo-faq]')->length)->toBe(0)
        ->and($xpath->query('//*[@data-tool-guide]/following-sibling::*[@data-seo-faq]//details')->length)->toBe(1)
        ->and($xpath->query('//*[@data-seo-toc-toggle]')->item(0)->getAttribute('class'))->toContain('absolute', 'bottom-7')
        ->and($xpath->query('//*[@data-seo-expand]')->item(0)->getAttribute('aria-controls'))->toBe('tool-seo-content');
    $schemas = [];
    foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
        $schemas[] = json_decode($script->textContent, true, 512, JSON_THROW_ON_ERROR);
    }
    $breadcrumbs = array_values(array_filter($schemas, fn (array $schema): bool => ($schema['@type'] ?? '') === 'BreadcrumbList'));
    expect($breadcrumbs)->toHaveCount(1);
    $items = $breadcrumbs[0]['itemListElement'];
    expect(array_column($items, 'position'))->toBe([1, 2])
        ->and(array_column($items, 'item'))->toBe([route('home'), url($path)])
        ->and($items[1]['name'])->toBe($xpath->query('//*[@data-tool-header]//nav//*[@aria-current="page"]')->item(0)->textContent);
    $faq = array_values(array_filter($schemas, fn (array $schema): bool => ($schema['@type'] ?? '') === 'FAQPage'));
    expect($faq)->toHaveCount(1)
        ->and($faq[0]['mainEntity'][0]['name'])->toBe('Dùng thế nào?')
        ->and($faq[0]['mainEntity'][0]['acceptedAnswer']['text'])->toBe('Nhập chỉ số gốc.');
})->with(['/tinh-tiem-nang', '/tinh-chi-so-trang-bi-nang-cap', '/tinh-tiem-nang-con-thieu', '/giai-captcha-nro']);

test('tool pages without a published article retain one useful heading and no empty guide', function (string $path): void {
    $html = $this->get($path)->assertOk()->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1)
        ->and($html)->toContain('data-tool-header', 'data-tool-workspace')
        ->not->toContain('data-tool-guide');
})->with(['/tinh-tiem-nang', '/tinh-chi-so-trang-bi-nang-cap', '/tinh-tiem-nang-con-thieu', '/giai-captcha-nro']);

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

test('all public tools use one shared hero breadcrumb and full width workspace', function (string $path): void {
    $html = $this->get($path)->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);

    expect($xpath->query('//*[@data-tool-hero][@data-tool-header]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-tool-hero]//nav//*[@aria-current="page"]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-tool-hero]//h1'))->toHaveCount(1)
        ->and($xpath->query('//h1'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-tool-workspace]'))->toHaveCount(1)
        ->and($xpath->query('//*[@data-tool-hero]')->item(0)->getAttribute('class'))->toContain('rounded-[10px]', 'bg-gradient-to-r');

    if ($path === '/thong-bao-game') {
        expect($xpath->query('//*[@data-tool-hero]//*[@data-nro-refresh]'))->toHaveCount(1);
    }
})->with(['/tinh-tiem-nang', '/tinh-tiem-nang-con-thieu', '/tinh-chi-so-trang-bi-nang-cap', '/giai-captcha-nro', '/ghep-anh-game', '/thong-bao-game', '/data-game']);

test('tool seo visibility follows the login option while keeping hero metadata and breadcrumb', function (string $path, bool $signedIn, bool $hide, bool $visible): void {
    app(ToolAvailabilityService::class)->update('test_potential', ['url' => $path, 'is_enabled' => true, 'maintenance_message' => '']);
    app(ToolSeoService::class)->update('test_potential', toolSeoPayload(['hide_when_authenticated' => $hide]));
    if ($signedIn) {
        $this->actingAs(User::factory()->create());
    }

    $response = $this->get($path)->assertOk()
        ->assertSee('data-tool-hero', false)
        ->assertSee('Hướng dẫn công cụ')
        ->assertSee('Giới thiệu công cụ.')
        ->assertSee('SEO title công cụ')
        ->assertSee('<meta name="description" content="SEO description công cụ">', false)
        ->assertSee('"@type":"BreadcrumbList"', false);
    if ($visible) {
        $response->assertSee('Nội dung hướng dẫn công cụ.')->assertSee('Dùng thế nào?')
            ->assertSee('data-tool-guide', false)->assertSee('"@type":"Article"', false)->assertSee('"@type":"FAQPage"', false);
    } else {
        $response->assertDontSee('Nội dung hướng dẫn công cụ.')->assertDontSee('Dùng thế nào?')
            ->assertDontSee('data-tool-guide', false)->assertDontSee('data-seo-faq', false)
            ->assertDontSee('"@type":"Article"', false)->assertDontSee('"@type":"FAQPage"', false);
    }
})->with(['/tinh-tiem-nang', '/thong-bao-game', '/data-game'])->with([
    'guest with hiding enabled' => [false, true, true],
    'member with hiding enabled' => [true, true, false],
    'member with hiding disabled' => [true, false, true],
]);

test('admin can switch login visibility without unpublishing and older payloads preserve the option', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $endpoint = '/api/admin-api/settings/services/test_potential/seo';
    $this->patchJson($endpoint, toolSeoPayload(['hide_when_authenticated' => true]))->assertOk()
        ->assertJsonPath('data.hide_when_authenticated', true)->assertJsonPath('data.is_published', true);
    $this->patchJson($endpoint, toolSeoPayload(['expected_version' => 1]))->assertOk()
        ->assertJsonPath('data.hide_when_authenticated', true);
    $this->patchJson($endpoint, toolSeoPayload(['hide_when_authenticated' => false, 'expected_version' => 2]))->assertOk()
        ->assertJsonPath('data.hide_when_authenticated', false)->assertJsonPath('data.is_published', true);
    $this->get('/tinh-tiem-nang')->assertOk()->assertSee('Nội dung hướng dẫn công cụ.');
});

test('tool seo rejects invalid login visibility values', function (mixed $value): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->patchJson('/api/admin-api/settings/services/test_potential/seo', toolSeoPayload(['hide_when_authenticated' => $value]))
        ->assertUnprocessable()->assertJsonPath('status', false)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'ẩn bài SEO khi đã đăng nhập'));
})->with(['invalid string' => ['yes'], 'null' => [null], 'array' => [[]]]);

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
