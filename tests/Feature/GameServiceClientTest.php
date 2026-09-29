<?php

use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;

test('client game service item opens a game picker without submenu links', function (): void {
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'game_services_enabled' => true,
        'status' => 'active',
    ]);
    GameService::factory()->for($game)->create(['status' => 'active']);

    $disabledGame = Game::factory()->create([
        'name' => 'Game chưa mở dịch vụ',
        'game_services_enabled' => false,
        'status' => 'active',
    ]);
    GameService::factory()->for($disabledGame)->create(['status' => 'active']);

    $response = $this->get(route('home'))->assertSuccessful();

    expect(substr_count($response->getContent(), 'data-game-service-picker-open'))->toBe(2);

    $response
        ->assertSee('data-game-service-picker-modal', false)
        ->assertSee('data-game-service-picker-link', false)
        ->assertSee('Dịch vụ game')
        ->assertSee('Ngọc Rồng Online')
        ->assertSee('href="'.route('game-services.show', ['game' => $game]).'"', false)
        ->assertDontSee('href="'.route('game-services.show', ['game' => $disabledGame]).'"', false)
        ->assertDontSee('data-game-service-menu', false);
});

test('game service page lists only active services for the selected game', function (): void {
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'game_services_enabled' => true,
        'status' => 'active',
    ]);
    $activeService = GameService::factory()->for($game)->create([
        'name' => 'Săn đệ tử',
        'slug' => 'san-de-tu',
        'description' => 'Mô tả không hiển thị ngoài thẻ.',
        'status' => 'active',
    ]);
    $minimumPackage = GameServicePackage::factory()->for($activeService, 'service')->create(['status' => 'active']);
    $maximumPackage = GameServicePackage::factory()->for($activeService, 'service')->create(['status' => 'active']);
    $inactivePackage = GameServicePackage::factory()->for($activeService, 'service')->create(['status' => 'inactive']);
    GameServicePackagePrice::factory()->for($minimumPackage, 'package')->create(['price' => 10000, 'status' => 'active']);
    GameServicePackagePrice::factory()->for($maximumPackage, 'package')->create(['price' => 25000, 'status' => 'active']);
    GameServicePackagePrice::factory()->for($inactivePackage, 'package')->create(['price' => 1000, 'status' => 'active']);
    GameService::factory()->for($game)->create([
        'name' => 'Dịch vụ đã tắt',
        'slug' => 'dich-vu-da-tat',
        'status' => 'inactive',
    ]);

    $this->get('/dich-vu-game-ngoc-rong-online')
        ->assertSuccessful()
        ->assertViewIs('client.game-services.show')
        ->assertSee('Dịch vụ game Ngọc Rồng Online')
        ->assertSee('Săn đệ tử')
        ->assertSee('href="'.route('game-services.service', ['game' => $game, 'gameService' => $activeService]).'"', false)
        ->assertSee('2 gói')
        ->assertSee('Giá từ 10.000đ - 25.000đ')
        ->assertDontSee('Mô tả không hiển thị ngoài thẻ.')
        ->assertDontSee('Dịch vụ đã tắt');
});

test('client opens a scoped game service detail page with active packages', function (): void {
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'game_services_enabled' => true,
        'status' => 'active',
    ]);
    $service = GameService::factory()->for($game)->create([
        'name' => 'Săn đệ tử',
        'slug' => 'san-de-tu',
        'description' => 'Dịch vụ săn đệ tử theo yêu cầu.',
        'seo_content' => [[
            'type' => 'paragraph',
            'children' => [['text' => 'Nội dung SEO riêng của dịch vụ.']],
        ]],
        'faqs' => [['question' => 'Bao lâu có kết quả?', 'answer' => 'Cộng tác viên sẽ tiếp nhận sớm nhất.']],
        'status' => 'active',
    ]);
    $server = GameServer::factory()->for($game)->create(['name' => 'Máy chủ 1']);
    $service->servers()->attach($server);
    $activePackage = GameServicePackage::factory()->for($service, 'service')->create([
        'name' => 'Gói săn nhanh',
        'status' => 'active',
    ]);
    $inactivePackage = GameServicePackage::factory()->for($service, 'service')->create([
        'name' => 'Gói đã tắt',
        'status' => 'inactive',
    ]);
    GameServicePackagePrice::factory()->for($activePackage, 'package')->create([
        'price' => 50000,
        'quantity_enabled' => false,
        'status' => 'active',
    ]);
    GameServicePackagePrice::factory()->for($inactivePackage, 'package')->create([
        'price' => 1000,
        'status' => 'active',
    ]);

    $this->get('/dich-vu-game-ngoc-rong-online/san-de-tu')
        ->assertSuccessful()
        ->assertViewIs('client.game-services.service')
        ->assertSee('Săn đệ tử')
        ->assertSee('Dịch vụ săn đệ tử theo yêu cầu.')
        ->assertSee('Gói săn nhanh')
        ->assertSee('50.000đ')
        ->assertSee('data-game-service-heading', false)
        ->assertSee('data-game-service-order-form', false)
        ->assertSee('5 đơn mới nhất')
        ->assertSee('Nội dung SEO riêng của dịch vụ.')
        ->assertSee('Bao lâu có kết quả?')
        ->assertDontSee('aspect-[21/7]', false)
        ->assertDontSee('Gói đã tắt');
});

test('guest can create a game service order from the service form', function (): void {
    $game = Game::factory()->create(['game_services_enabled' => true, 'status' => 'active']);
    $service = GameService::factory()->for($game)->create([
        'payload_fields' => [[
            'key' => 'account',
            'label' => 'Tài khoản',
            'placeholder' => '',
            'required' => true,
            'regex' => '',
            'type' => 'text',
            'options' => [],
            'min' => null,
            'max' => null,
            'step' => null,
        ], [
            'key' => 'password',
            'label' => 'Mật khẩu',
            'placeholder' => '',
            'required' => true,
            'regex' => '',
            'type' => 'password',
            'options' => [],
            'min' => null,
            'max' => null,
            'step' => null,
        ]],
        'status' => 'active',
    ]);
    $server = GameServer::factory()->for($game)->create(['name' => 'Máy chủ 2']);
    $service->servers()->attach($server);
    $package = GameServicePackage::factory()->for($service, 'service')->create(['name' => 'Gói nhiệm vụ', 'status' => 'active']);
    $price = GameServicePackagePrice::factory()->for($package, 'package')->create([
        'price' => 30000,
        'quantity_enabled' => true,
        'min_quantity' => 2,
        'max_quantity' => 5,
        'status' => 'active',
    ]);

    $this->post(route('game-services.orders.store', ['game' => $game, 'gameService' => $service]), [
        'package_id' => $package->id,
        'server_id' => $server->id,
        'quantity' => 3,
        'email' => 'PLAYER@example.com',
        'payload' => ['account' => 'player01', 'password' => 'secret123'],
    ])->assertRedirect(route('game-services.service', ['game' => $game, 'gameService' => $service]))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('game_service_orders', [
        'game_service_id' => $service->id,
        'game_service_package_price_id' => $price->id,
        'game_server_id' => $server->id,
        'email' => 'player@example.com',
        'quantity' => 3,
        'unit_price' => 30000,
        'total_amount' => 90000,
        'status' => 'pending',
    ]);
    expect(GameServiceOrder::query()->firstOrFail()->payload)->toBe(['account' => 'player01', 'password' => 'secret123']);
});

test('service page only displays the five latest orders', function (): void {
    $game = Game::factory()->create(['game_services_enabled' => true, 'status' => 'active']);
    $service = GameService::factory()->for($game)->create(['status' => 'active']);

    foreach (range(1, 6) as $number) {
        GameServiceOrder::factory()->for($service, 'service')->create([
            'game_id' => $game->id,
            'package_name' => "Gói gần đây {$number}",
            'created_at' => now()->addMinutes($number),
        ]);
    }

    $this->get(route('game-services.service', ['game' => $game, 'gameService' => $service]))
        ->assertSuccessful()
        ->assertSee('Gói gần đây 6')
        ->assertSee('Gói gần đây 2')
        ->assertDontSee('Gói gần đây 1');
});

test('game service detail route scopes the service to its game', function (): void {
    $game = Game::factory()->create(['game_services_enabled' => true, 'status' => 'active']);
    $otherGame = Game::factory()->create(['game_services_enabled' => true, 'status' => 'active']);
    $service = GameService::factory()->for($otherGame)->create(['status' => 'active']);

    $this->get(route('game-services.service', ['game' => $game, 'gameService' => $service]))->assertNotFound();
});

test('game service page is unavailable when the game cannot receive services', function (): void {
    $game = Game::factory()->create([
        'game_services_enabled' => false,
        'status' => 'active',
    ]);
    GameService::factory()->for($game)->create(['status' => 'active']);

    $this->get(route('game-services.show', ['game' => $game]))->assertNotFound();
});
