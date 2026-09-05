<?php

use App\Models\AdminAuditLog;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateProgram;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Support\EditorContentRenderer;

test('admin manages affiliate announcements and changes are audited', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $richContent = [[
        'type' => 'paragraph',
        'children' => [[
            'text' => 'Xem chính sách',
            'bold' => true,
            'href' => 'https://napcarot.com/chinh-sach',
            'target' => '_blank',
        ]],
    ], [
        'type' => 'image',
        'src' => '/storage/uploads/image/affiliate-policy.webp',
        'alt' => 'Chính sách cộng tác viên',
    ]];

    $createResponse = $this->actingAs($admin)->postJson('http://napcarot.com/api/admin-api/affiliate/announcements', [
        'site_id' => $main->id,
        'title' => 'Chính sách hoa hồng mới',
        'content' => $richContent,
        'is_pinned' => true,
        'is_published' => true,
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Chính sách hoa hồng mới')
        ->assertJsonPath('data.content.0.children.0.href', 'https://napcarot.com/chinh-sach')
        ->assertJsonPath('data.is_pinned', true)
        ->assertJsonPath('data.is_published', true);

    $announcementId = (int) $createResponse->json('data.id');
    $announcement = AffiliateAnnouncement::query()->withoutGlobalScopes()->findOrFail($announcementId);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/affiliate/announcements')
        ->assertOk()
        ->assertJsonPath('data.announcements.0.id', $announcementId)
        ->assertJsonPath('data.announcements.0.content_html', app(EditorContentRenderer::class)->renderNodes($richContent)->toHtml())
        ->assertJsonPath('data.selected_site_id', $main->id);

    $this->actingAs($admin)->putJson("http://napcarot.com/api/admin-api/affiliate/announcements/{$announcementId}", [
        'site_id' => $main->id,
        'title' => 'Chính sách hoa hồng đã cập nhật',
        'content' => $richContent,
        'is_pinned' => false,
        'is_published' => false,
    ])->assertOk()
        ->assertJsonPath('data.title', 'Chính sách hoa hồng đã cập nhật')
        ->assertJsonPath('data.is_pinned', false)
        ->assertJsonPath('data.is_published', false);

    $this->actingAs($admin)
        ->deleteJson("http://napcarot.com/api/admin-api/affiliate/announcements/{$announcementId}")
        ->assertOk();

    $this->assertModelMissing($announcement);
    expect(AdminAuditLog::query()->withoutGlobalScopes()->whereIn('action', [
        'affiliate_announcement_created',
        'affiliate_announcement_updated',
        'affiliate_announcement_deleted',
    ])->count())->toBe(3);
});

test('affiliate announcement rejects unsafe editor content', function (array $content): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);

    $this->actingAs($admin)->postJson('http://napcarot.com/api/admin-api/affiliate/announcements', [
        'site_id' => $main->id,
        'title' => 'Ảnh không an toàn',
        'content' => $content,
        'is_pinned' => false,
        'is_published' => true,
    ])->assertUnprocessable();
})->with([
    'base64 image' => [
        [['type' => 'image', 'src' => 'data:image/png;base64,AAAA', 'alt' => 'Ảnh']],
    ],
    'javascript link' => [
        [['type' => 'paragraph', 'children' => [['text' => 'Mở link', 'href' => 'javascript:alert(1)']]]],
    ],
]);

test('affiliate home shows pinned published announcements first and only for the current site', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $otherSite = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);

    $olderPinned = AffiliateAnnouncement::factory()->pinned()->create([
        'tenant_id' => $main->id,
        'title' => 'Thông báo ghim',
        'published_at' => now()->subDays(2),
    ]);
    $newerRegular = AffiliateAnnouncement::factory()->create([
        'tenant_id' => $main->id,
        'title' => 'Thông báo mới',
        'published_at' => now(),
    ]);
    AffiliateAnnouncement::factory()->draft()->create(['tenant_id' => $main->id, 'title' => 'Bản nháp']);
    AffiliateAnnouncement::factory()->pinned()->create(['tenant_id' => $otherSite->id, 'title' => 'Thông báo website khác']);

    $this->actingAs($user)
        ->getJson('http://napcarot.com/api/client/affiliate/home')
        ->assertOk()
        ->assertJsonCount(2, 'data.announcements')
        ->assertJsonPath('data.announcements.0.id', $olderPinned->id)
        ->assertJsonPath('data.announcements.0.is_pinned', true)
        ->assertJsonPath('data.announcements.0.content_html', app(EditorContentRenderer::class)->renderNodes($olderPinned->content)->toHtml())
        ->assertJsonPath('data.announcements.1.id', $newerRegular->id)
        ->assertJsonMissing(['title' => 'Bản nháp'])
        ->assertJsonMissing(['title' => 'Thông báo website khác']);
});

test('child site admin cannot change an affiliate announcement from another site', function (): void {
    $firstSite = Tenant::factory()->create();
    $secondSite = Tenant::factory()->create();
    TenantDomain::factory()->for($firstSite)->create(['domain' => 'affiliate-announcement.test']);
    $admin = User::factory()->create(['tenant_id' => $firstSite->id, 'role' => 'admin']);
    $foreignAnnouncement = AffiliateAnnouncement::factory()->create(['tenant_id' => $secondSite->id]);

    $this->actingAs($admin)->putJson("http://affiliate-announcement.test/api/admin-api/affiliate/announcements/{$foreignAnnouncement->id}", [
        'site_id' => $secondSite->id,
        'title' => 'Không được sửa',
        'content' => [['type' => 'paragraph', 'children' => [['text' => 'Không được sửa nội dung.']]]],
        'is_pinned' => true,
        'is_published' => true,
    ])->assertForbidden();

    expect($foreignAnnouncement->fresh()->title)->not->toBe('Không được sửa');
});
