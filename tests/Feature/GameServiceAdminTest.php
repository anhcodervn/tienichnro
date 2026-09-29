<?php

use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\User;

function validGameServicePayload(Game $game, GameServer $server): array
{
    return [
        'game_id' => $game->id,
        'name' => 'Đổi tên nhân vật',
        'slug' => 'doi-ten-nhan-vat',
        'code' => 'rename-character',
        'description' => 'Dịch vụ đổi tên nhân vật trong game.',
        'background_image' => '/storage/uploads/image/doi-ten-nhan-vat.webp',
        'server_ids' => [$server->id],
        'payload_fields' => [
            [
                'key' => 'character_name',
                'label' => 'Tên nhân vật',
                'placeholder' => 'Nhập tên nhân vật hiện tại',
                'required' => true,
                'regex' => '^[A-Za-z0-9_]+$',
                'type' => 'text',
                'options' => [],
                'min' => null,
                'max' => null,
                'step' => null,
            ],
            [
                'key' => 'account_password',
                'label' => 'Mật khẩu game',
                'placeholder' => 'Nhập mật khẩu game',
                'required' => true,
                'regex' => '',
                'type' => 'password',
                'options' => [],
                'min' => null,
                'max' => null,
                'step' => null,
            ],
        ],
        'seo_content' => [[
            'type' => 'paragraph',
            'children' => [['text' => 'Nội dung SEO dịch vụ.']],
        ]],
        'faqs' => [['question' => 'Dịch vụ xử lý bao lâu?', 'answer' => 'Thời gian tùy từng gói dịch vụ.']],
        'status' => 'active',
        'sort_order' => 1,
    ];
}

function validGameServicePackagePayload(GameService $service): array
{
    return [
        'game_service_id' => $service->id,
        'name' => 'Gói đổi tên',
        'code' => 'rename-pack',
        'description' => 'Gói dịch vụ đổi tên.',
        'status' => 'active',
        'sort_order' => 1,
        'prices' => [[
            'price' => 50000,
            'collaborator_price' => 35000,
            'quantity_enabled' => false,
            'min_quantity' => 9,
            'max_quantity' => 99,
        ]],
    ];
}

test('game service admin endpoints require a platform admin', function (): void {
    $this->getJson('/api/admin-api/game-service-games')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/game-service-games')
        ->assertForbidden();
});

test('admin enables an existing catalog game for services without duplicating game identity', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create([
        'name' => 'Ngọc Rồng Online',
        'slug' => 'ngoc-rong-online',
        'provider_service_code' => 'nro',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-games/{$game->id}", [
            'game_services_enabled' => true,
            'provider_service_code' => 'nro-service',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Ngọc Rồng Online')
        ->assertJsonPath('data.slug', 'ngoc-rong-online')
        ->assertJsonPath('data.code', 'nro-service')
        ->assertJsonPath('data.game_services_enabled', true);

    expect($game->refresh()->game_services_enabled)->toBeTrue()
        ->and($game->provider_service_code)->toBe('nro-service');
});

test('admin creates a service with dynamic payload fields and servers from the same game', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['game_services_enabled' => true]);
    $server = GameServer::factory()->for($game)->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/game-services', validGameServicePayload($game, $server))
        ->assertCreated()
        ->assertJsonPath('data.game_id', $game->id)
        ->assertJsonPath('data.background_image', '/storage/uploads/image/doi-ten-nhan-vat.webp')
        ->assertJsonPath('data.server_ids.0', $server->id)
        ->assertJsonPath('data.payload_fields.0.key', 'character_name')
        ->assertJsonPath('data.payload_fields.1.type', 'password')
        ->assertJsonPath('data.seo_content.0.type', 'paragraph')
        ->assertJsonPath('data.faqs.0.question', 'Dịch vụ xử lý bao lâu?');

    $service = GameService::query()->findOrFail($response->json('data.id'));

    expect($service->servers()->pluck('game_servers.id')->all())->toBe([$server->id])
        ->and($service->background_image)->toBe('/storage/uploads/image/doi-ten-nhan-vat.webp')
        ->and($service->payload_fields[0]['label'])->toBe('Tên nhân vật')
        ->and($service->payload_fields[1]['type'])->toBe('password')
        ->and($service->seo_content[0]['type'])->toBe('paragraph')
        ->and($service->faqs[0]['answer'])->toBe('Thời gian tùy từng gói dịch vụ.');
});

test('service requires its own background image', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['game_services_enabled' => true]);
    $server = GameServer::factory()->for($game)->create();
    $payload = validGameServicePayload($game, $server);
    unset($payload['background_image']);

    $this->actingAs($admin)
        ->postJson('/api/admin-api/game-services', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('background_image');
});

test('service rejects a server that belongs to another game', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['game_services_enabled' => true]);
    $foreignServer = GameServer::factory()->create();

    $this->actingAs($admin)
        ->postJson('/api/admin-api/game-services', validGameServicePayload($game, $foreignServer))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('server_ids');
});

test('admin creates a service package with exactly one price', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = GameService::factory()->create();

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/game-service-packages', validGameServicePackagePayload($service))
        ->assertCreated()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath('data.prices.0.collaborator_price', 35000)
        ->assertJsonPath('data.prices.0.quantity_enabled', false)
        ->assertJsonPath('data.prices.0.min_quantity', 1)
        ->assertJsonPath('data.prices.0.max_quantity', 1);

    $package = GameServicePackage::query()->findOrFail($response->json('data.id'));

    expect($package->prices()->count())->toBe(1)
        ->and($package->prices()->where('code', 'default')->value('label'))->toBe('Gói đổi tên')
        ->and($package->prices()->where('code', 'default')->value('collaborator_price'))->toBe(35000)
        ->and($package->prices()->where('code', 'default')->value('status'))->toBe('active')
        ->and($package->prices()->where('code', 'default')->value('min_quantity'))->toBe(1);
});

test('service package rejects more than one price', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = GameService::factory()->create();
    $payload = validGameServicePackagePayload($service);
    $payload['prices'][] = $payload['prices'][0];

    $this->actingAs($admin)
        ->postJson('/api/admin-api/game-service-packages', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('prices');
});

test('package status controls its single price status without exposing price controls', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = GameService::factory()->create();
    $package = GameServicePackage::factory()->for($service, 'service')->create(['status' => 'active']);
    $price = GameServicePackagePrice::factory()->for($package, 'package')->create([
        'code' => 'legacy-price',
        'status' => 'active',
    ]);
    $payload = validGameServicePackagePayload($service);
    $payload['status'] = 'inactive';

    $this->actingAs($admin)
        ->putJson("/api/admin-api/game-service-packages/{$package->id}", $payload)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.prices')
        ->assertJsonPath('data.prices.0.code', 'legacy-price')
        ->assertJsonPath('data.prices.0.status', 'inactive');

    expect($price->refresh()->status)->toBe('inactive')
        ->and($package->prices()->count())->toBe(1);
});

test('admin filters and updates game service orders while preserving snapshots', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $service = GameService::factory()->for($game)->create();
    $package = GameServicePackage::factory()->for($service, 'service')->create();
    $price = GameServicePackagePrice::factory()->for($package, 'package')->create(['price' => 50000]);
    $order = GameServiceOrder::factory()->create([
        'game_id' => $game->id,
        'game_service_id' => $service->id,
        'game_service_package_id' => $package->id,
        'game_service_package_price_id' => $price->id,
        'game_server_id' => $server->id,
        'game_name' => $game->name,
        'service_name' => $service->name,
        'package_name' => $package->name,
        'price_label' => $price->label,
        'server_name' => $server->name,
        'payload' => ['character_name' => 'Goku'],
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders?status=pending&search='.$order->code)
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.code', $order->code)
        ->assertJsonPath('data.data.0.payload.character_name', 'Goku');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", [
            'status' => 'completed',
            'admin_note' => 'Đã bàn giao dịch vụ.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.admin_note', 'Đã bàn giao dịch vụ.');

    expect($order->refresh()->completed_at)->not->toBeNull()
        ->and($order->service_name)->toBe($service->name);
});
