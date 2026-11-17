<?php

use App\Features\Admin\Seo\Services\HomeSeoService;
use App\Models\Setting;
use App\Models\User;

test('only platform admins can manage home seo settings', function (): void {
    $this->getJson('/api/admin-api/seo/home')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/seo/home')
        ->assertForbidden();
});

test('admin can save and load dedicated home seo settings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = [
        'meta_title' => 'Nạp game Teamobi an toàn tại NapCarot',
        'meta_description' => 'Trang nạp game Teamobi có bảng giá rõ ràng và hỗ trợ nhanh chóng.',
        'meta_keywords' => 'nạp game teamobi, nạp carot, napcarot',
        'h1' => 'Nạp game Teamobi nhanh chóng',
        'article_title' => 'Hướng dẫn nạp game tại NapCarot',
        'content' => [
            ['type' => 'paragraph', 'children' => [['text' => 'Nội dung SEO tùy chỉnh cho trang chủ.']]],
        ],
        'faqs' => [
            ['question' => 'Nạp game mất bao lâu?', 'answer' => 'Thời gian được hiển thị theo từng gói nạp.'],
        ],
        'is_published' => true,
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/seo/home', $payload)
        ->assertOk()
        ->assertJsonPath('data.meta_title', $payload['meta_title'])
        ->assertJsonPath('data.meta_keywords', $payload['meta_keywords'])
        ->assertJsonPath('data.is_published', true);

    expect(Setting::query()->where('key', 'home_seo_meta_title')->value('value'))->toBe($payload['meta_title'])
        ->and(Setting::query()->where('key', 'home_seo_meta_keywords')->value('value'))->toBe($payload['meta_keywords'])
        ->and(Setting::query()->where('key', 'home_seo_content')->value('type'))->toBe('json');

    $this->actingAs($admin)
        ->getJson('/api/admin-api/seo/home')
        ->assertOk()
        ->assertJsonPath('data.meta_keywords', $payload['meta_keywords'])
        ->assertJsonPath('data.h1', $payload['h1'])
        ->assertJsonPath('data.faqs.0.question', $payload['faqs'][0]['question']);
});

test('published home seo requires its search and article fields', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/seo/home', ['is_published' => true])
        ->assertUnprocessable();
});

test('published home seo is rendered safely with matching faq schema', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->patchJson('/api/admin-api/seo/home', [
        'meta_title' => 'Meta trang chủ tùy chỉnh',
        'meta_description' => 'Mô tả trang chủ tùy chỉnh cho kết quả tìm kiếm.',
        'meta_keywords' => 'nạp carot tùy chỉnh, nạp game nhanh',
        'h1' => 'H1 trang chủ tùy chỉnh',
        'article_title' => 'Bài hướng dẫn tùy chỉnh',
        'content' => [
            ['type' => 'paragraph', 'children' => [
                ['text' => 'Nội dung SEO an toàn'],
                ['text' => ' liên kết lỗi', 'href' => 'javascript:alert(1)'],
            ]],
        ],
        'faqs' => [
            ['question' => 'Câu hỏi riêng?', 'answer' => 'Câu trả lời riêng.'],
        ],
        'is_published' => true,
    ])->assertOk();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>Meta trang chủ tùy chỉnh</title>', false)
        ->assertSee('content="Mô tả trang chủ tùy chỉnh cho kết quả tìm kiếm."', false)
        ->assertSee('<meta name="keywords" content="nạp carot tùy chỉnh, nạp game nhanh">', false)
        ->assertDontSee('H1 trang chủ tùy chỉnh')
        ->assertSee('sm:text-[26px]">Meta trang chủ tùy chỉnh</h1>', false)
        ->assertSee('text-slate-600">Mô tả trang chủ tùy chỉnh cho kết quả tìm kiếm.</p>', false)
        ->assertSee('Bài hướng dẫn tùy chỉnh')
        ->assertSee('Nội dung SEO an toàn')
        ->assertSee('Câu hỏi riêng?')
        ->assertSee('FAQPage')
        ->assertSee('data-seo-article', false)
        ->assertSee('data-seo-toc-toggle', false)
        ->assertSee('data-seo-faq', false)
        ->assertDontSee('javascript:alert(1)', false);
});

test('home seo can be published without duplicate titles while preserving the existing article heading', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = app(HomeSeoService::class);
    $settings->update([
        'article_title' => 'Hướng dẫn đã lưu',
        'h1' => 'Tiêu đề cũ',
    ]);

    $this->actingAs($admin)->patchJson('/api/admin-api/seo/home', [
        'meta_title' => 'Tiêu đề dùng chung',
        'meta_description' => 'Mô tả dùng chung.',
        'content' => [
            ['type' => 'paragraph', 'children' => [['text' => 'Bài viết trang chủ.']]],
        ],
        'faqs' => [['question' => 'Câu hỏi?', 'answer' => 'Câu trả lời.']],
        'is_published' => true,
    ])->assertOk()
        ->assertJsonPath('data.h1', 'Tiêu đề dùng chung')
        ->assertJsonPath('data.article_title', 'Hướng dẫn đã lưu');

    $this->get(route('home'))->assertOk()
        ->assertSee('sm:text-[26px]">Tiêu đề dùng chung</h1>', false)
        ->assertSee('text-slate-600">Mô tả dùng chung.</p>', false)
        ->assertSee('Hướng dẫn đã lưu')
        ->assertSee('Bài viết trang chủ.')
        ->assertSee('Câu hỏi?');
});

test('hiding the home article and faq keeps the shared title and description visible', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->patchJson('/api/admin-api/seo/home', [
        'meta_title' => 'Trang chủ vẫn hiển thị',
        'meta_description' => 'Mô tả vẫn hiển thị.',
        'content' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung cần ẩn.']]]],
        'faqs' => [['question' => 'Câu hỏi cần ẩn?', 'answer' => 'Trả lời cần ẩn.']],
        'is_published' => false,
    ])->assertOk();

    $this->get(route('home'))->assertOk()
        ->assertSee('sm:text-[26px]">Trang chủ vẫn hiển thị</h1>', false)
        ->assertSee('text-slate-600">Mô tả vẫn hiển thị.</p>', false)
        ->assertDontSee('Nội dung cần ẩn.')
        ->assertDontSee('Câu hỏi cần ẩn?');
});
