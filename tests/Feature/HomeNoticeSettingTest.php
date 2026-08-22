<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;

test('only admins can manage homepage notice settings', function (): void {
    $this->getJson('/api/admin-api/settings/homepage')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/homepage', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/homepage')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/homepage', [])->assertForbidden();
});

test('homepage does not invent an announcement when admin content is empty', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('role="note"', false)
        ->assertDontSee('Nạp Carot tối đa 10 gói mỗi lần');
});

test('admin can write homepage notice with tinymce content and homepage renders it safely', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.home_notice_title', 'Thông báo quan trọng')
        ->json('data.settings');

    $noticeContent = [
        [
            'type' => 'paragraph',
            'children' => [
                ['text' => 'Khuyến mãi 15%', 'bold' => true],
                ['text' => '<script>alert("xss")</script>'],
                [
                    'text' => ' Mở ưu đãi',
                    'bold' => true,
                    'color' => '#0f766e',
                    'href' => 'https://napcarot.com/uu-dai?from=notice&day=1',
                    'target' => '_blank',
                ],
            ],
        ],
        [
            'type' => 'list',
            'ordered' => false,
            'items' => [
                [['text' => 'Mỗi mã QR chỉ quét một lần.']],
                [['text' => 'Không cung cấp mật khẩu game.']],
            ],
        ],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/homepage', [
            ...$settings,
            'home_notice_title' => 'Ưu đãi hôm nay',
            'home_notice_content' => $noticeContent,
            'home_notice_is_published' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.home_notice_title', 'Ưu đãi hôm nay')
        ->assertJsonPath('data.settings.home_notice_content.0.children.0.bold', true);

    $stored = Setting::query()->where('key', 'home_notice_content')->firstOrFail();

    expect($stored->type)->toBe('json')
        ->and(json_decode((string) $stored->value, true))->toBe($noticeContent);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Ưu đãi hôm nay')
        ->assertSee('class="home-notice-header"', false)
        ->assertSee('<strong>Khuyến mãi 15%</strong>', false)
        ->assertSee(
            '<a href="https://napcarot.com/uu-dai?from=notice&amp;day=1" target="_blank" rel="noopener noreferrer"><span style="color:#0f766e"><strong> Mở ưu đãi</strong></span></a>',
            false,
        )
        ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
        ->assertSee('<li>Mỗi mã QR chỉ quét một lần.</li>', false)
        ->assertDontSee('<details class="home-notice-banner"', false)
        ->assertDontSee('Xem chi tiết')
        ->assertDontSee('<script>alert("xss")</script>', false);
});

test('homepage notice rejects unsafe tinymce links', function (string $href): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/homepage')
        ->assertOk()
        ->json('data.settings');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/homepage', [
            ...$settings,
            'home_notice_content' => [
                [
                    'type' => 'paragraph',
                    'children' => [['text' => 'Liên kết', 'href' => $href]],
                ],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.home_notice_content.0', 'Nội dung thông báo chứa định dạng không được hỗ trợ.');
})->with([
    'javascript scheme' => 'javascript:alert(1)',
    'data scheme' => 'data:text/html,<script>alert(1)</script>',
    'vbscript scheme' => 'vbscript:msgbox(1)',
    'protocol relative url' => '//example.com/phishing',
    'backslash protocol relative url' => '/\\example.com/phishing',
    'whitespace obfuscation' => "java\nscript:alert(1)",
]);

test('homepage tab reads existing setting keys and content pages no longer own them', function (): void {
    app(SettingStore::class)->putMany([
        'home_notice_title' => 'Thông báo đã lưu trước đó',
        'home_notice_content' => [
            ['type' => 'paragraph', 'children' => [['text' => 'Nội dung cũ được giữ nguyên.']]],
        ],
        'home_notice_is_published' => true,
    ]);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/homepage')
        ->assertOk()
        ->assertJsonPath('data.settings.home_notice_title', 'Thông báo đã lưu trước đó')
        ->assertJsonPath('data.settings.home_notice_content.0.children.0.text', 'Nội dung cũ được giữ nguyên.');

    $contentSettings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/content-pages')
        ->assertOk()
        ->json('data.settings');

    expect($contentSettings)->not->toHaveKeys([
        'home_notice_title',
        'home_notice_content',
        'home_notice_is_published',
    ]);
});

test('homepage notice can be hidden and rejects unsupported rich content', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/homepage')
        ->assertOk()
        ->json('data.settings');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/homepage', [
            ...$settings,
            'home_notice_title' => 'Thông báo phải ẩn',
            'home_notice_is_published' => false,
        ])
        ->assertOk();

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Thông báo phải ẩn')
        ->assertDontSee('role="note"', false);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/homepage', [
            ...$settings,
            'home_notice_content' => [
                ['type' => 'image', 'src' => 'javascript:alert(1)'],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('data.errors.home_notice_content.0', 'Nội dung thông báo chứa định dạng không được hỗ trợ.');
});
