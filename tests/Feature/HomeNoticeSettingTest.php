<?php

use App\Models\Game;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;

test('only admins can manage homepage notice settings', function (): void {
    $this->getJson('/api/admin-api/settings/homepage')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/homepage', [])->assertUnauthorized();
    $this->getJson('/api/admin-api/settings/popup-notice')->assertUnauthorized();
    $this->patchJson('/api/admin-api/settings/popup-notice', [])->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/admin-api/settings/homepage')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/homepage', [])->assertForbidden();
    $this->actingAs($user)->getJson('/api/admin-api/settings/popup-notice')->assertForbidden();
    $this->actingAs($user)->patchJson('/api/admin-api/settings/popup-notice', [])->assertForbidden();
});

test('admin can configure the homepage popup notice', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/popup-notice')
        ->assertOk()
        ->assertJsonPath('data.settings.home_popup_is_published', false)
        ->assertJsonPath('data.settings.home_popup_display_mode', 'modal')
        ->assertJsonPath('data.settings.home_popup_allow_dismiss', false)
        ->json('data.settings');

    $content = [[
        'type' => 'paragraph',
        'children' => [[
            'text' => 'Mở trang hỗ trợ',
            'bold' => true,
            'color' => '#dc2626',
            'href' => '/chat',
        ]],
    ]];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/popup-notice', [
            ...$settings,
            'home_popup_title' => 'Bảo trì dịch vụ',
            'home_popup_content' => $content,
            'home_popup_is_published' => true,
            'home_popup_display_mode' => 'popup',
            'home_popup_allow_dismiss' => true,
            'home_popup_dismiss_hours' => 12,
        ])
        ->assertOk()
        ->assertJsonPath('data.settings.home_popup_title', 'Bảo trì dịch vụ')
        ->assertJsonPath('data.settings.home_popup_content.0.children.0.href', '/chat')
        ->assertJsonPath('data.settings.home_popup_display_mode', 'popup')
        ->assertJsonPath('data.settings.home_popup_dismiss_hours', '12');

    expect(Setting::query()->where('key', 'home_popup_content')->firstOrFail()->type)->toBe('json')
        ->and(Setting::query()->where('key', 'home_popup_is_published')->firstOrFail()->type)->toBe('boolean');
});

test('homepage popup renders safely for guests and authenticated users', function (): void {
    app(SettingStore::class)->putMany([
        'home_popup_title' => 'Ưu đãi thành viên',
        'home_popup_content' => [[
            'type' => 'paragraph',
            'children' => [[
                'text' => 'Xem chi tiết',
                'bold' => true,
                'color' => '#dc2626',
                'href' => 'https://napcarot.com/uu-dai',
                'target' => '_blank',
            ]],
        ]],
        'home_popup_is_published' => true,
        'home_popup_display_mode' => 'modal',
        'home_popup_allow_dismiss' => true,
        'home_popup_dismiss_hours' => 48,
    ]);

    $assertPopup = function ($response): void {
        $response
            ->assertOk()
            ->assertSee('data-home-popup', false)
            ->assertSee('data-dismiss-enabled="true"', false)
            ->assertSee('data-dismiss-hours="48"', false)
            ->assertSee('data-display-mode="modal"', false)
            ->assertSee('Ưu đãi thành viên')
            ->assertSeeText('Đã hiểu')
            ->assertSeeText('Đóng trong 48 giờ')
            ->assertDontSeeText('Đã hiểu và đóng trong 48 giờ')
            ->assertSee(
                '<a href="https://napcarot.com/uu-dai" target="_blank" rel="noopener noreferrer"><span style="color:#dc2626"><strong>Xem chi tiết</strong></span></a>',
                false,
            );
    };

    $assertPopup($this->get(route('home')));
    $assertPopup($this->actingAs(User::factory()->create())->get(route('home')));
});

test('homepage popup validates display and dismissal settings', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/popup-notice', [
            'home_popup_title' => 'Thông báo',
            'home_popup_content' => [],
            'home_popup_is_published' => true,
            'home_popup_display_mode' => 'banner',
            'home_popup_allow_dismiss' => true,
            'home_popup_dismiss_hours' => 0,
        ])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'data' => [
                'errors' => ['home_popup_display_mode', 'home_popup_dismiss_hours'],
            ],
        ]);
});

test('homepage does not invent an announcement when admin content is empty', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('role="note"', false)
        ->assertDontSee('Nạp Carot tối đa 10 gói mỗi lần');
});

test('admin can write notice content that stays off homepage and renders safely on game landing', function (): void {
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
        ->assertDontSee('Ưu đãi hôm nay')
        ->assertDontSee('class="home-notice-header"', false);

    $game = Game::factory()->create();

    $this->get(route('topup.game', ['game' => $game]))
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

test('game landing notice accepts a bare domain and renders a clickable https link', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $settings = $this->actingAs($admin)
        ->getJson('/api/admin-api/settings/homepage')
        ->assertOk()
        ->json('data.settings');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/homepage', [
            ...$settings,
            'home_notice_content' => [[
                'type' => 'paragraph',
                'children' => [[
                    'text' => 'Mở cộng đồng',
                    'href' => 'facebook.com/napcarot',
                    'target' => '_blank',
                ]],
            ]],
        ])
        ->assertOk();

    $game = Game::factory()->create();

    $this->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertSee(
            '<a href="https://facebook.com/napcarot" target="_blank" rel="noopener noreferrer">Mở cộng đồng</a>',
            false,
        );
});

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
