<?php

use App\Models\Setting;
use App\Models\User;

test('only admins can manage support channels', function (): void {
    $this->getJson('/api/admin-api/settings/support-channels')->assertUnauthorized();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/admin-api/settings/support-channels', ['support_channels' => []])
        ->assertForbidden();
});

test('admin can save support channel icons and links', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $channels = [
        [
            'icon' => ' https://cdn.example.com/zalo.png ',
            'url' => ' https://zalo.me/84901234567 ',
            'is_active' => true,
        ],
        [
            'icon' => '/images/facebook.svg',
            'url' => 'https://facebook.com/napcarot',
            'is_active' => false,
        ],
    ];

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/support-channels', ['support_channels' => $channels])
        ->assertOk()
        ->assertJsonPath('data.settings.support_channels.0.icon', 'https://cdn.example.com/zalo.png')
        ->assertJsonPath('data.settings.support_channels.0.url', 'https://zalo.me/84901234567')
        ->assertJsonPath('data.settings.support_channels.1.is_active', false);

    $stored = Setting::query()->where('key', 'support_channels')->firstOrFail();

    expect($stored->type)->toBe('json');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-floating-support', false)
        ->assertSee('data-floating-support-list', false)
        ->assertSee('https://cdn.example.com/zalo.png', false)
        ->assertSee('https://zalo.me/84901234567', false)
        ->assertDontSee('/images/facebook.svg', false);
});

test('support channels reject unsafe icon and destination urls', function (string $field, string $value): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $channel = [
        'icon' => 'https://cdn.example.com/support.png',
        'url' => 'https://example.com/support',
        'is_active' => true,
    ];
    $channel[$field] = $value;

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/support-channels', ['support_channels' => [$channel]])
        ->assertUnprocessable()
        ->assertJsonStructure([
            'data' => [
                'errors' => ["support_channels.0.{$field}"],
            ],
        ]);
})->with([
    'javascript icon' => ['icon', 'javascript:alert(1)'],
    'data destination' => ['url', 'data:text/html,<script>alert(1)</script>'],
    'protocol relative destination' => ['url', '//example.com/support'],
]);

test('website chat page is no longer exposed', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/chat')
        ->assertNotFound();
});
