<?php

use App\Models\Game;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\TopupPackage;
use App\Models\User;
use Database\Seeders\SeoContentSeeder;
use Illuminate\Support\Carbon;

test('admin can configure all seo page types and a price page service with faq', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = Game::factory()->create(['name' => 'Ngọc Rồng Online']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/seo/post-options')
        ->assertOk()
        ->assertJsonPath('data.services.0.id', $service->id)
        ->assertJsonPath('data.services.0.name', 'Ngọc Rồng Online');

    $response = $this->actingAs($admin)->postJson('/api/admin-api/seo/posts', [
        'type' => 'price',
        'service_id' => $service->id,
        'title' => 'Bảng giá nạp Ngọc Rồng Online',
        'slug' => 'bang-gia-nap-ngoc-rong-online',
        'excerpt' => 'Giá được cập nhật trực tiếp từ dịch vụ.',
        'content' => [],
        'faq' => [[
            'question' => 'Giá có tự cập nhật không?',
            'answer' => 'Có, bảng giá đọc trực tiếp từ package.',
        ]],
        'robots' => 'index,follow',
        'status' => 'draft',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.type', 'price')
        ->assertJsonPath('data.service_id', $service->id)
        ->assertJsonPath('data.faq.0.question', 'Giá có tự cập nhật không?');

    expect(SeoPost::query()->firstOrFail()->service->is($service))->toBeTrue();

    $this->actingAs($admin)->postJson('/api/admin-api/seo/posts', [
        'type' => 'price',
        'title' => 'Bảng giá thiếu dịch vụ',
        'slug' => 'bang-gia-thieu-dich-vu',
        'robots' => 'index,follow',
        'status' => 'draft',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Page bảng giá phải được liên kết với một dịch vụ.');

    $this->actingAs($admin)->postJson('/api/admin-api/seo/posts', [
        'type' => 'guide',
        'service_id' => $service->id,
        'title' => 'Hướng dẫn không được gắn dịch vụ',
        'slug' => 'huong-dan-khong-gan-dich-vu',
        'robots' => 'index,follow',
        'status' => 'draft',
    ])->assertUnprocessable();
});

test('price seo page renders live active packages and package update time without storing prices in content', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Bảng giá',
        'slug' => 'bang-gia',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $service = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'reward_label' => 'Lượng',
        'sort_order' => 1,
    ]);
    $olderPackage = TopupPackage::factory()->for($service)->create([
        'name' => 'Gói 200',
        'denomination' => 200000,
        'price' => 180000,
        'original_price' => 200000,
        'carot_amount' => 240,
        'sort_order' => 10,
        'updated_at' => Carbon::parse('2026-08-01 08:00:00'),
    ]);
    $latestPackage = TopupPackage::factory()->for($service)->create([
        'name' => 'Gói 100',
        'denomination' => 100000,
        'price' => 90000,
        'original_price' => 100000,
        'carot_amount' => 120,
        'sort_order' => 20,
        'updated_at' => Carbon::parse('2026-08-15 09:30:00'),
    ]);
    TopupPackage::factory()->for($service)->inactive()->create([
        'name' => 'Gói ngừng bán',
        'denomination' => 500000,
        'price' => 450000,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'type' => 'price',
        'service_id' => $service->id,
        'title' => 'Bảng giá nạp Ngọc Rồng Online',
        'slug' => 'bang-gia-nap-ngoc-rong-online',
        'excerpt' => 'Bảng giá động, minh bạch và luôn theo dữ liệu bán hàng.',
        'content' => [
            ['type' => 'heading', 'level' => 1, 'children' => [['text' => 'Thông tin bảng giá']]],
            ['type' => 'paragraph', 'children' => [['text' => 'Nội dung tư vấn không lưu bảng giá.']]],
        ],
        'faq' => [[
            'question' => 'Giá trên trang lấy từ đâu?',
            'answer' => 'Giá lấy trực tiếp từ package đang bán.',
        ]],
        'focus_keyword' => 'bảng giá nạp game, nạp ngọc rồng',
        'robots' => 'index,follow',
        'article_schema' => true,
        'breadcrumb_schema' => true,
        'status' => 'published',
        'published_at' => Carbon::parse('2026-07-01 00:00:00'),
    ]);
    $url = route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]);

    $response = $this->get($url)
        ->assertOk()
        ->assertSee('<meta name="keywords" content="bảng giá nạp game, nạp ngọc rồng">', false)
        ->assertSee('Bảng giá Ngọc Rồng Online')
        ->assertSee('100.000đ')
        ->assertSee('90.000đ')
        ->assertSee('120 Lượng')
        ->assertSee('200.000đ')
        ->assertSee('180.000đ')
        ->assertDontSee('500.000đ')
        ->assertSeeInOrder(['200.000đ', '100.000đ'])
        ->assertSee('Giá cập nhật 15/08/2026')
        ->assertSee('FAQPage')
        ->assertSee('WebPage')
        ->assertDontSee('"@type":"Article"', false)
        ->assertSee('<meta property="og:type" content="website">', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1)
        ->and($post->content[1]['children'][0]['text'])->toBe('Nội dung tư vấn không lưu bảng giá.');

    $latestPackage->timestamps = false;
    $latestPackage->forceFill([
        'price' => 85000,
        'updated_at' => Carbon::parse('2026-09-08 14:45:00'),
    ])->save();
    $latestPackage->timestamps = true;

    $this->get($url)
        ->assertOk()
        ->assertSee('85.000đ')
        ->assertDontSee('90.000đ')
        ->assertSee('Giá cập nhật 08/09/2026');

    expect($olderPackage->fresh()->price)->toBe('180000.00')
        ->and($post->fresh()->content[1]['children'][0]['text'])->toBe('Nội dung tư vấn không lưu bảng giá.');
});

test('knowledge and guide seo pages keep article schema and do not query a price table', function (string $type): void {
    $category = SeoCategory::query()->create([
        'name' => 'Kiến thức',
        'slug' => 'kien-thuc-'.$type,
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'type' => $type,
        'title' => 'Nội dung '.$type,
        'slug' => 'noi-dung-'.$type,
        'content' => [['type' => 'paragraph', 'children' => [['text' => 'Nội dung '.$type]]]],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->get(route('seo.show', ['categorySlug' => $category->slug, 'postSlug' => $post->slug]))
        ->assertOk()
        ->assertSee('"@type":"Article"', false)
        ->assertDontSee('dynamic-price-table-title', false);
})->with(['knowledge', 'guide']);

test('seo content seeder links known price drafts only when their service exists', function (): void {
    $service = Game::factory()->create(['slug' => 'ngoc-rong-online']);

    $this->seed(SeoContentSeeder::class);

    $pricePost = SeoPost::query()->where('slug', 'bang-gia-nap-ngoc-rong-online')->firstOrFail();

    expect($pricePost->type)->toBe('price')
        ->and($pricePost->service_id)->toBe($service->id)
        ->and(SeoPost::query()->where('slug', 'cach-nap-ngoc-rong-online-bang-carot')->value('type'))->toBe('guide')
        ->and(SeoPost::query()->where('slug', 'bang-gia-nap-carot')->value('type'))->toBe('knowledge');
});

test('data migration upgrades existing known seo pages without changing unrelated posts', function (): void {
    $service = Game::factory()->create(['slug' => 'ninja-school']);
    $pricePost = SeoPost::query()->create([
        'title' => 'Bảng giá Ninja School Online',
        'slug' => 'bang-gia-nap-ninja-school-online',
        'robots' => 'noindex,follow',
        'status' => 'draft',
    ]);
    $guidePost = SeoPost::query()->create([
        'title' => 'Cách nạp Avatar',
        'slug' => 'cach-nap-game-avatar',
        'robots' => 'noindex,follow',
        'status' => 'draft',
    ]);
    $unrelatedPost = SeoPost::query()->create([
        'title' => 'Bài tùy chỉnh của admin',
        'slug' => 'bai-tuy-chinh-cua-admin',
        'robots' => 'noindex,follow',
        'status' => 'draft',
    ]);
    $migration = require database_path('migrations/2026_09_08_162137_backfill_dynamic_seo_post_types.php');

    $migration->up();

    expect($pricePost->refresh()->type)->toBe('price')
        ->and($pricePost->service_id)->toBe($service->id)
        ->and($guidePost->refresh()->type)->toBe('guide')
        ->and($unrelatedPost->refresh()->type)->toBe('knowledge');
});
