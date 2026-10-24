<?php

use App\Features\Admin\Seo\Services\SeoService;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->category = SeoCategory::query()->create(['name' => 'Kiến thức', 'slug' => 'kien-thuc', 'robots' => 'index,follow', 'is_active' => true]);
    $this->payload = [
        'seo_category_id' => $this->category->id, 'type' => 'guide', 'language' => 'vi', 'slug' => 'bai-seo-moi',
        'seo_title' => 'Tiêu đề SEO riêng', 'seo_description' => 'Mô tả metadata riêng', 'focus_keyword' => 'keyword', 'meta_keywords' => 'keyword, bài seo',
        'content' => [['type' => 'heading', 'level' => 1, 'children' => [['text' => 'H1 trong nội dung']]], ['type' => 'paragraph', 'children' => [['text' => 'Đoạn giới thiệu đầu tiên.']]]],
        'faq' => [['question' => 'Câu hỏi?', 'answer' => 'Trả lời.']], 'robots' => 'index,follow', 'canonical_url' => null,
        'cover_image' => '/storage/cover.webp', 'cover_alt' => 'Alt ảnh SEO', 'article_schema' => false, 'breadcrumb_schema' => false,
    ];
    $this->actingAs($this->admin);
});

test('creating publishes now clears scheduling and derives internal fields without a revision', function (): void {
    $this->freezeTime();
    $id = $this->postJson('/api/admin-api/seo/posts', [...$this->payload, 'status' => 'draft', 'published_at' => now()->addMonth(), 'scheduled_at' => now()->addDay()])
        ->assertCreated()->assertJsonPath('data.status', 'published')->assertJsonPath('data.scheduled_at', null)
        ->assertJsonPath('data.title', 'H1 trong nội dung')->assertJsonPath('data.excerpt', 'Đoạn giới thiệu đầu tiên.')->json('data.id');
    $post = SeoPost::query()->findOrFail($id);
    expect($post->published_at->timestamp)->toBe(now()->timestamp)->and($post->lock_version)->toBe(1);
    $this->getJson('/api/admin-api/seo/posts/'.$id.'/revisions')->assertOk()->assertJsonCount(0, 'data');
    $this->assertDatabaseCount('seo_post_revisions', 0);
});

test('updating captures the full published snapshot and reading old data never modifies the post', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', $this->payload)->assertCreated()->json('data.id');
    $post = SeoPost::query()->findOrFail($id);
    $original = $post->only($post->getFillable());
    $this->travel(1)->minutes();
    $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$this->payload, 'slug' => 'bai-seo-thay-doi', 'seo_title' => 'SEO mới', 'expected_version' => 1])->assertOk();
    $revision = $post->revisions()->firstOrFail();
    expect($revision->snapshot)->toEqual(json_decode(json_encode($original), true));
    $this->getJson('/api/admin-api/seo/posts/'.$id.'/revisions')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', 'bai-seo-moi')->assertJsonPath('data.0.seo_title', 'Tiêu đề SEO riêng')->assertJsonMissingPath('data.0.snapshot');
    $this->getJson('/api/admin-api/seo/posts/'.$id.'/revisions/'.$revision->id)->assertOk()->assertJsonPath('data.snapshot.article_schema', false)->assertJsonPath('data.snapshot.canonical_url', null);
    expect($post->fresh()->slug)->toBe('bai-seo-thay-doi');
    $restored = $revision->snapshot;
    unset($restored['published_at'], $restored['scheduled_at']);
    $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$restored, 'expected_version' => 2])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.slug', 'bai-seo-moi')->assertJsonPath('data.article_schema', false);
    $this->assertDatabaseCount('seo_posts', 1);
    $this->assertDatabaseCount('seo_post_revisions', 2);
});

test('history retains exactly the ten most recent prior published versions in descending order', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', $this->payload)->assertCreated()->json('data.id');
    foreach (range(1, 13) as $version) {
        $this->travel(1)->seconds();
        $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$this->payload, 'seo_title' => 'SEO version '.$version, 'expected_version' => $version])->assertOk();
    }
    $this->getJson('/api/admin-api/seo/posts/'.$id.'/revisions')->assertOk()->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.seo_title', 'SEO version 12')->assertJsonPath('data.9.seo_title', 'SEO version 3');
    $this->assertDatabaseCount('seo_post_revisions', 10);
});

test('draft and scheduled legacy posts publish without creating a prior published version', function (string $status): void {
    $post = SeoPost::query()->create(['title' => 'Legacy', 'slug' => 'legacy', 'status' => $status, 'scheduled_at' => now()->addDay()]);
    $this->patchJson('/api/admin-api/seo/posts/'.$post->id, $this->payload)->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.scheduled_at', null);
    $this->assertDatabaseCount('seo_post_revisions', 0);
})->with(['draft', 'scheduled']);

test('stale form saves conflict before changing the post or creating a snapshot', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', $this->payload)->assertCreated()->json('data.id');
    $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$this->payload, 'seo_title' => 'Newest', 'expected_version' => 1])->assertOk();
    $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$this->payload, 'seo_title' => 'Stale', 'expected_version' => 1])->assertConflict();
    expect(SeoPost::query()->findOrFail($id)->seo_title)->toBe('Newest');
    $this->assertDatabaseCount('seo_post_revisions', 1);
});

test('failed updates roll back snapshot creation and history pruning', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', $this->payload)->assertCreated()->json('data.id');
    $other = SeoPost::query()->create(['title' => 'Other', 'slug' => 'taken-slug']);
    $post = SeoPost::query()->findOrFail($id);
    expect(fn () => app(SeoService::class)->upsertPost([...$this->payload, 'slug' => $other->slug], $post))->toThrow(QueryException::class);
    expect($post->fresh()->slug)->toBe('bai-seo-moi')->and($post->fresh()->lock_version)->toBe(1);
    $this->assertDatabaseCount('seo_post_revisions', 0);
});

test('revision endpoints enforce existing admin permissions and scope ids to the owning post', function (): void {
    $post = SeoPost::query()->create(['title' => 'Post', 'slug' => 'post']);
    $other = SeoPost::query()->create(['title' => 'Other', 'slug' => 'other']);
    $revision = $other->revisions()->create(['snapshot' => ['title' => 'Private old post']]);
    $this->getJson('/api/admin-api/seo/posts/'.$post->id.'/revisions/'.$revision->id)->assertNotFound();
    $this->actingAs(User::factory()->create())->getJson('/api/admin-api/seo/posts/'.$other->id.'/revisions')->assertForbidden();
    $this->getJson('/api/admin-api/seo/posts/'.$other->id.'/revisions/'.$revision->id)->assertForbidden();
    auth()->forgetGuards();
    $this->getJson('/api/admin-api/seo/posts/'.$other->id.'/revisions')->assertUnauthorized();
});

test('outer transaction rollback restores pruned history and the previous article', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', $this->payload)->assertCreated()->json('data.id');
    $post = SeoPost::query()->findOrFail($id);
    foreach (range(1, 10) as $number) {
        $post->revisions()->create(['snapshot' => ['seo_title' => 'Prior '.$number]]);
    }
    $oldIds = $post->revisions()->orderBy('id')->pluck('id')->all();
    expect(fn () => DB::transaction(function () use ($post): void {
        app(SeoService::class)->upsertPost([...$this->payload, 'seo_title' => 'Rolled back'], $post);
        throw new RuntimeException('Rollback after history pruning');
    }))->toThrow(RuntimeException::class);
    expect($post->revisions()->orderBy('id')->pluck('id')->all())->toBe($oldIds)
        ->and($post->fresh()->seo_title)->toBe('Tiêu đề SEO riêng')
        ->and($post->fresh()->lock_version)->toBe(1);
});

test('public rendering preserves a content h1 without duplicate introduction and reflects metadata', function (): void {
    $id = $this->postJson('/api/admin-api/seo/posts', [...$this->payload, 'article_schema' => true, 'breadcrumb_schema' => true, 'language' => 'en'])->assertCreated()->json('data.id');
    $url = route('seo.show', ['categorySlug' => $this->category->slug, 'postSlug' => 'bai-seo-moi']);
    $response = $this->get($url)->assertOk()->assertSee('<title>Tiêu đề SEO riêng</title>', false)->assertSee('content="Mô tả metadata riêng"', false)
        ->assertSee('"@type":"Article"', false)->assertSee('"@type":"BreadcrumbList"', false)->assertSee('lang="en"', false)->assertSee('alt="Alt ảnh SEO"', false);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//h1')->length)->toBe(1)->and($xpath->query('//p[normalize-space(.)="Đoạn giới thiệu đầu tiên."]')->length)->toBe(1);
    $this->get('/sitemap-articles.xml')->assertOk()->assertSee($url);
    $this->patchJson('/api/admin-api/seo/posts/'.$id, [...$this->payload, 'robots' => 'noindex,follow', 'canonical_url' => 'https://example.com/canonical', 'expected_version' => 1])->assertOk()->assertJsonPath('data.status', 'published');
    $this->get($url)->assertOk()->assertSee('content="noindex,follow"', false)->assertSee('href="https://example.com/canonical"', false)->assertDontSee('"@type":"Article"', false)->assertDontSee('"@type":"BreadcrumbList"', false);
    $this->get('/sitemap-articles.xml')->assertOk()->assertDontSee($url);
});

test('old articles without a content h1 keep their fallback heading and introduction', function (): void {
    $post = SeoPost::query()->create(['title' => 'Legacy H1', 'excerpt' => 'Legacy intro', 'slug' => 'legacy-article', 'content' => [['type' => 'paragraph', 'children' => [['text' => 'Body']]]], 'status' => 'published', 'published_at' => now()]);
    $this->get(route('seo.legacy.show', $post->slug))->assertOk()->assertSee('Legacy H1')->assertSee('Legacy intro')->assertViewHas('contentHasH1', false);
});
