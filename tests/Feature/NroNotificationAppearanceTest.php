<?php

use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\Boss;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

test('notification state colors and readable labels survive page ajax and realtime snapshots', function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    Boss::factory()->create(['name' => 'Broly', 'game_names' => ['Broly 1', 'Broly 2'], 'respawn_seconds' => 1800]);
    $ids = [];
    foreach (['BOSS Broly 1 xuất hiện tại Núi khỉ vàng', 'BOSS Broly 2 xuất hiện tại Núi khỉ vàng', 'BOSS đang di chuyển', 'Thông báo bảo trì <script>alert(1)</script>'] as $content) {
        $ids[] = $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => $content, 'occurred_at' => now()->subMinute()->toISOString()])->assertCreated()->json('data.id');
    }
    $this->postJson('/api/admin-api/nro/notifies', ['server_code' => 1, 'content' => 'Player: đã tiêu diệt được Broly 2 mọi người đều ngưỡng mộ.', 'occurred_at' => now()->toISOString()])->assertOk()->assertJsonPath('data.id', $ids[1]);
    $page = $this->get('/thong-bao-game?server_code=1')->assertOk();
    $ajax = $this->getJson('/thong-bao-game?server_code=1')->assertOk()->json('data.html');
    $snapshot = app(NroNotificationFeedService::class)->snapshot(['server_code' => 1, 'limit' => 10]);
    expect($ajax)->toBe($snapshot['html']);
    foreach ([[$ids[0], 'living', 'border-emerald-500', 'bg-emerald-700', 'Đã xuất hiện'], [$ids[1], 'dead', 'border-rose-500', 'bg-rose-700', 'Đã bị tiêu diệt'], [$ids[2], 'global', 'border-amber-500', 'bg-amber-700', 'Boss global'], [$ids[3], 'notification', 'border-sky-500', 'bg-sky-700', 'OTHER']] as [$id, $state, $border, $badge, $label]) {
        preg_match('/<article\b[^>]*data-notify-id="'.$id.'".*?<\/article>/s', $ajax, $matches);
        expect($matches[0] ?? '')->toContain('data-notify-state="'.$state.'"', $border, $badge, 'text-white', 'text-lg font-extrabold', $label);
        $page->assertSee('data-notify-state="'.$state.'"', false);
    }
    $page->assertSee('nro-feed-page bg-slate-50', false)
        ->assertSee('grid gap-3 transition-opacity', false)
        ->assertDontSee('max-sm:w-[111.111111111%]', false)
        ->assertDontSee('max-sm:[zoom:0.9]', false);
    $page->assertSee('Boss: Broly 1')->assertSee('Map: Núi khỉ vàng')->assertSee('Người tiêu diệt: Player')->assertSee('data-nro-respawn=', false);
    expect($ajax)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')->not->toContain('<script>alert(1)</script>');
});
