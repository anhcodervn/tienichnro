<?php

use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\User;

test('admin can delete an empty seo category', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = SeoCategory::query()->create([
        'name' => 'Danh muc trong',
        'slug' => 'danh-muc-trong',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin-api/seo/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Đã xóa danh mục SEO.')
        ->assertJsonPath('data.detached_post_count', 0);

    $this->assertModelMissing($category);
});

test('deleting an seo category preserves its posts as uncategorized', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = SeoCategory::query()->create([
        'name' => 'Tin game',
        'slug' => 'tin-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    $post = SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'title' => 'Bai viet duoc giu lai',
        'slug' => 'bai-viet-duoc-giu-lai',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'draft',
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin-api/seo/categories/{$category->id}")
        ->assertOk()
        ->assertJsonPath(
            'message',
            'Đã xóa danh mục SEO và chuyển 1 bài viết về trạng thái chưa phân loại.',
        )
        ->assertJsonPath('data.detached_post_count', 1);

    $this->assertModelMissing($category);
    $this->assertModelExists($post);
    expect($post->refresh()->seo_category_id)->toBeNull();
});

test('seo category deletion requires an admin account', function (): void {
    $category = SeoCategory::query()->create([
        'name' => 'Danh muc bao ve',
        'slug' => 'danh-muc-bao-ve',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);

    $this->deleteJson("/api/admin-api/seo/categories/{$category->id}")
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->deleteJson("/api/admin-api/seo/categories/{$category->id}")
        ->assertForbidden();

    $this->assertModelExists($category);
});

test('deleting a missing seo category returns not found', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->deleteJson('/api/admin-api/seo/categories/999999')
        ->assertNotFound();
});
