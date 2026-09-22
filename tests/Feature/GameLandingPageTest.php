<?php

use App\Enums\OrderStatus;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\SeoCategory;
use App\Models\SeoPost;
use App\Models\Setting;
use App\Models\TopupPackage;

test('game landing contains notice topup pending rewards and seo content without exposing customer data', function (): void {
    Setting::query()->updateOrCreate(['key' => 'home_notice_title'], ['value' => 'Bảo trì nhanh', 'type' => 'string']);
    Setting::query()->updateOrCreate(['key' => 'home_notice_is_published'], ['value' => '1', 'type' => 'boolean']);
    Setting::query()->updateOrCreate(['key' => 'home_notice_content'], [
        'value' => json_encode([[
            'type' => 'paragraph',
            'children' => [['text' => 'Thông báo dùng chung từ trang chủ.']],
        ]], JSON_UNESCAPED_UNICODE),
        'type' => 'json',
    ]);

    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'reward_label' => 'Ngọc',
        'seo_title' => 'Nạp Ngọc Rồng Online giá tốt',
        'seo_description' => 'Mô tả SEO riêng cho NRO.',
        'content' => "Bài SEO riêng cho game.\n<script>alert('xss')</script>",
    ]);
    GameServer::factory()->create(['game_id' => $game->id, 'status' => 'active']);
    $package = TopupPackage::factory()->create([
        'game_id' => $game->id,
        'name' => 'Gói 100 Ngọc',
        'denomination' => 100000,
        'carot_amount' => 100,
        'reward_x2_amount' => 200,
        'reward_x3_amount' => 300,
        'first_topup_reward_amount' => 250,
        'status' => 'active',
    ]);
    TopupPackage::factory()->inactive()->create([
        'game_id' => $game->id,
        'name' => 'Gói đã đóng',
    ]);
    $otherGame = Game::factory()->create();

    foreach (range(1, 4) as $position) {
        Order::factory()->create([
            'game_id' => $game->id,
            'topup_package_id' => $package->id,
            'package_name' => "Pending package {$position}",
            'quantity' => $position,
            'order_status' => OrderStatus::Pending,
            'email' => "private{$position}@example.test",
            'normalized_email' => "private{$position}@example.test",
            'game_account' => "secret-account-{$position}",
            'created_at' => now()->subMinutes($position),
        ]);
    }
    Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => $package->id,
        'package_name' => 'Completed package',
        'order_status' => OrderStatus::Completed,
    ]);
    Order::factory()->create([
        'game_id' => $otherGame->id,
        'package_name' => 'Other game pending package',
        'order_status' => OrderStatus::Pending,
    ]);

    $category = SeoCategory::query()->create([
        'name' => 'Hướng dẫn game',
        'slug' => 'huong-dan-game',
        'robots' => 'index,follow',
        'is_active' => true,
    ]);
    SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'service_id' => $game->id,
        'title' => 'Cách nạp NRO an toàn',
        'slug' => 'cach-nap-nro-an-toan',
        'excerpt' => 'Hướng dẫn chi tiết.',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'published',
        'published_at' => now(),
    ]);
    SeoPost::query()->create([
        'seo_category_id' => $category->id,
        'service_id' => $game->id,
        'title' => 'Bài nháp không hiển thị',
        'slug' => 'bai-nhap-khong-hien-thi',
        'content' => [],
        'robots' => 'index,follow',
        'status' => 'draft',
    ]);

    $url = route('topup.game', ['game' => $game]);
    $response = $this->get($url)
        ->assertOk()
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<title>Nạp Ngọc Rồng Online giá tốt</title>', false)
        ->assertSee('Mô tả SEO riêng cho NRO.')
        ->assertSee('Bảo trì nhanh')
        ->assertSee('Thông báo dùng chung từ trang chủ.')
        ->assertSee('Nạp game Ngọc Rồng Online')
        ->assertSee('data-game-landing-hero', false)
        ->assertSee('h-16 w-16', false)
        ->assertSee('sm:h-28 sm:w-28', false)
        ->assertSee('text-xl font-extrabold leading-snug', false)
        ->assertSee('data-detached-steps="true"', false)
        ->assertDontSee('data-topup-form-header', false)
        ->assertSeeInOrder([
            'data-topup-step="1"',
            'Bước 1: Nhập tài khoản',
            'data-topup-step="2"',
            'Bước 2: Chọn mệnh giá',
            'data-topup-step="3"',
            'Bước 3: Thanh toán',
        ], false)
        ->assertDontSee('Tóm tắt đơn')
        ->assertSee('Gói 100 Ngọc')
        ->assertSee('100 Ngọc')
        ->assertSee('200 Ngọc')
        ->assertSee('Pending package 1')
        ->assertSee('Pending package 2')
        ->assertSee('Pending package 3')
        ->assertDontSee('Pending package 4')
        ->assertDontSee('Completed package')
        ->assertDontSee('Other game pending package')
        ->assertDontSee('Gói đã đóng')
        ->assertSee('Bài SEO riêng cho game.')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee("<script>alert('xss')</script>", false)
        ->assertSee('Cách nạp NRO an toàn')
        ->assertDontSee('Bài nháp không hiển thị')
        ->assertSee('BreadcrumbList');

    expect(substr_count($response->getContent(), 'data-pending-order'))->toBe(3)
        ->and(substr_count($response->getContent(), 'data-topup-step-card'))->toBe(3)
        ->and(substr_count($response->getContent(), '<h1'))->toBe(1);

    foreach (range(1, 4) as $position) {
        $response->assertDontSee("private{$position}@example.test")
            ->assertDontSee("secret-account-{$position}");
    }
});

test('homepage omits notice topup and pending order blocks', function (): void {
    $game = Game::factory()->create();
    GameServer::factory()->create(['game_id' => $game->id, 'status' => 'active']);
    TopupPackage::factory()->create(['game_id' => $game->id, 'status' => 'active']);
    Setting::query()->updateOrCreate(['key' => 'home_notice_title'], ['value' => 'Không hiển thị ở homepage', 'type' => 'string']);
    Setting::query()->updateOrCreate(['key' => 'home_notice_is_published'], ['value' => '1', 'type' => 'boolean']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Không hiển thị ở homepage')
        ->assertDontSee('home-notice-header', false)
        ->assertDontSee('data-topup-form', false)
        ->assertDontSee('data-recent-pending-orders', false)
        ->assertDontSee('data-pending-order', false)
        ->assertDontSee('data-topup-step="1"', false)
        ->assertDontSee('Bước 1: Nhập tài khoản');
});

test('flat game route wins over configured seo landing and legacy route redirects permanently', function (): void {
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
    ]);
    TopupPackage::factory()->create(['game_id' => $game->id]);

    $flatUrl = '/nap-game-ngoc-rong-online';
    $this->get($flatUrl)
        ->assertOk()
        ->assertSee('data-topup-form', false)
        ->assertSee('Nạp game Ngọc Rồng Online');

    $this->get('/nap-game/ngoc-rong-online')
        ->assertRedirect($flatUrl)
        ->assertStatus(301);
});

test('inactive games are unavailable and only active games appear in the game sitemap', function (): void {
    $activeGame = Game::factory()->create(['slug' => 'active-game']);
    $inactiveGame = Game::factory()->inactive()->create(['slug' => 'inactive-game']);

    $this->get('/nap-game-inactive-game')->assertNotFound();
    $this->get('/nap-game-missing-game')->assertNotFound();

    $this->get(route('sitemap.games'))
        ->assertOk()
        ->assertSee(route('topup.game', ['game' => $activeGame]))
        ->assertDontSee('/nap-game-inactive-game');
});
