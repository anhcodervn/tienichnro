<?php

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Features\Client\Wallet\Services\WalletService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderMessage;
use App\Models\GameServiceOrderProgress;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Support\SettingStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

test('admin review count only includes orders waiting for completion approval', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    GameServiceOrder::factory()->count(3)->create(['status' => 'review']);
    GameServiceOrder::factory()->create(['status' => 'processing']);
    GameServiceOrder::factory()->create(['status' => 'completed']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders/review-count')
        ->assertSuccessful()
        ->assertJsonPath('data.count', 3);
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

test('service rich description is sanitized and rendered as html', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['game_services_enabled' => true, 'status' => 'active']);
    $server = GameServer::factory()->for($game)->create();
    $payload = validGameServicePayload($game, $server);
    $payload['description'] = <<<'HTML'
<h2 style="color: #15803d" onclick="alert(1)">Dịch vụ nổi bật</h2>
<p>Nội dung <strong>nhấn mạnh</strong>.</p>
<img src="/storage/editor/service.webp" alt="Ảnh dịch vụ" onerror="alert(1)">
<img src="https://evil.example/image.webp" alt="Ảnh ngoài">
<script>alert('xss')</script>
HTML;

    $response = $this->actingAs($admin)
        ->postJson('/api/admin-api/game-services', $payload)
        ->assertCreated();

    $service = GameService::query()->findOrFail($response->json('data.id'));

    expect($service->description)
        ->toContain('<h2 style="color: #15803d">Dịch vụ nổi bật</h2>')
        ->toContain('<img src="/storage/editor/service.webp" alt="Ảnh dịch vụ">')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('evil.example')
        ->not->toContain('<script');

    $this->get(route('game-services.service', ['game' => $game, 'gameService' => $service]))
        ->assertSuccessful()
        ->assertSee('<meta name="description" content="Dịch vụ nổi bật Nội dung nhấn mạnh.">', false)
        ->assertSee('data-game-service-description="'.$service->id.'"', false)
        ->assertSee('data-client-image-viewer', false)
        ->assertSee('<h2 style="color: #15803d">Dịch vụ nổi bật</h2>', false)
        ->assertSee('src="/storage/editor/service.webp"', false)
        ->assertDontSee('onclick=', false)
        ->assertDontSee('evil.example', false)
        ->assertDontSee('&lt;h2', false);

    $updatePayload = validGameServicePayload($game, $server);
    $updatePayload['description'] = '<p onmouseover="alert(1)">Nội dung cập nhật</p><iframe src="https://evil.example"></iframe>';

    $this->actingAs($admin)
        ->putJson("/api/admin-api/game-services/{$service->id}", $updatePayload)
        ->assertSuccessful();

    expect($service->refresh()->description)
        ->toBe('<p>Nội dung cập nhật</p>')
        ->not->toContain('onmouseover')
        ->not->toContain('iframe');
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
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $service = GameService::factory()->for($game)->create();
    $package = GameServicePackage::factory()->for($service, 'service')->create();
    $price = GameServicePackagePrice::factory()->for($package, 'package')->create(['price' => 50000]);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
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
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", [
            'status' => 'completed',
            'admin_note' => 'Đã bàn giao dịch vụ.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $order->forceFill(['status' => 'review'])->save();
    $proofPath = "game-service-orders/{$order->code}/completion/proof.webp";
    Storage::disk('local')->put($proofPath, 'proof');
    GameServiceOrderProgress::factory()->create([
        'game_service_order_id' => $order->id,
        'user_id' => $collaborator->id,
        'type' => GameServiceOrderProgress::TYPE_COMPLETION,
        'image_path' => $proofPath,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders?exclude_status=review&search='.$order->code)
        ->assertSuccessful()
        ->assertJsonCount(0, 'data.data');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/approve-completion", [
            'admin_note' => 'Đã kiểm tra báo cáo hoàn thành.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.admin_note', 'Đã kiểm tra báo cáo hoàn thành.');

    expect($order->refresh()->completed_at)->not->toBeNull()
        ->and($order->service_name)->toBe($service->name);
});

test('admin cannot approve an invalid completion report and can return the order to processing', function (): void {
    Storage::fake('local');
    $admin = User::factory()->create(['role' => 'admin']);
    $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'review',
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/approve-completion")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('completion_report');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", [
            'status' => 'failed',
            'admin_note' => 'Không được kết thúc đơn tại màn duyệt.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", [
            'status' => 'processing',
            'admin_note' => 'CTV cần bổ sung lại bằng chứng.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'processing');

    expect($order->refresh()->status)->toBe('processing')
        ->and($order->completed_at)->toBeNull();
});

test('admin can fail an order with customer refund and collaborator hold reversal', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $customer = User::factory()->create(['tenant_id' => $admin->tenant_id]);
    $collaborator = User::factory()->create(['tenant_id' => $admin->tenant_id, 'role' => User::ROLE_COLLABORATOR]);
    $customerWallet = Wallet::query()
        ->where('user_id', $customer->id)
        ->where('type', Wallet::TYPE_MAIN)
        ->firstOrFail();
    $customerWallet->forceFill(['balance' => 100000])->save();
    $order = GameServiceOrder::factory()->for($customer)->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'processing',
        'total_amount' => 50000,
        'collaborator_total_cost' => 32000,
    ]);

    DB::transaction(function () use ($customer, $order): void {
        app(WalletService::class)->debit(
            $customer,
            50000,
            GameServiceOrder::class,
            $order->id,
            'Thanh toán đơn '.$order->code,
            idempotencyKey: (string) Str::uuid(),
        );
        app(AffiliateWalletService::class)->holdGameServiceOrder($order);
    });

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/refund", [
            'status' => 'failed',
            'admin_note' => 'Dịch vụ không thể hoàn thành, hoàn lại toàn bộ tiền.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'failed');

    $collaboratorWallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_COLLABORATOR)
        ->firstOrFail();

    expect((int) $customerWallet->refresh()->balance)->toBe(100000)
        ->and((int) $collaboratorWallet->work_hold_balance)->toBe(0)
        ->and($order->refresh()->collaborator_refunded_at)->not->toBeNull()
        ->and($order->collaborator_refund_transaction_id)->not->toBeNull();

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/refund", [
            'status' => 'failed',
            'admin_note' => 'Không được hoàn lần hai.',
        ])
        ->assertUnprocessable();
});

test('admin game service order summary only settles completed orders while each order keeps its settlement', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    GameServiceOrder::factory()->create([
        'status' => 'completed',
        'total_amount' => 100000,
        'collaborator_unit_cost' => 60000,
        'collaborator_total_cost' => 60000,
        'gross_profit' => 40000,
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => '1.0000',
        'pit_rate' => '0.5000',
        'estimated_vat' => 1000,
        'estimated_pit' => 500,
        'estimated_tax' => 1500,
        'net_profit' => 38500,
        'profit_margin' => '38.5000',
    ]);
    GameServiceOrder::factory()->create([
        'status' => 'completed',
        'total_amount' => 50000,
        'collaborator_unit_cost' => 52000,
        'collaborator_total_cost' => 52000,
        'gross_profit' => -2000,
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => '1.0000',
        'pit_rate' => '0.5000',
        'estimated_vat' => 500,
        'estimated_pit' => 250,
        'estimated_tax' => 750,
        'net_profit' => -2750,
        'profit_margin' => '-5.5000',
    ]);
    GameServiceOrder::factory()->create([
        'status' => 'pending',
        'total_amount' => 999999,
        'collaborator_total_cost' => 1,
        'gross_profit' => 999998,
        'estimated_tax' => 1,
        'net_profit' => 999997,
    ]);
    GameServiceOrder::factory()->create([
        'status' => 'completed',
        'total_amount' => 75000,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.approved_orders', 3)
        ->assertJsonPath('data.summary.settled_orders', 2)
        ->assertJsonPath('data.summary.revenue', 150000)
        ->assertJsonPath('data.summary.collaborator_cost', 112000)
        ->assertJsonPath('data.summary.gross_profit', 38000)
        ->assertJsonPath('data.summary.estimated_tax', 2250)
        ->assertJsonPath('data.summary.net_profit', 35750)
        ->assertJsonPath('data.summary.loss_orders', 1)
        ->assertJsonPath('data.summary.legacy_orders', 1);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders?status=pending')
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.net_profit', 999997)
        ->assertJsonPath('data.summary.approved_orders', 0)
        ->assertJsonPath('data.summary.settled_orders', 0)
        ->assertJsonPath('data.summary.revenue', 0)
        ->assertJsonPath('data.summary.net_profit', 0);
});

test('legacy game service order settlements can be backfilled without overwriting partial snapshots', function (): void {
    Tenant::factory()->create(['is_main' => true]);
    app(SettingStore::class)->putMany([
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => '1.0000',
        'pit_rate' => '0.5000',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $price = GameServicePackagePrice::factory()->create(['collaborator_price' => 20000]);
    $legacyOrder = GameServiceOrder::factory()->for($price, 'price')->create([
        'quantity' => 3,
        'unit_price' => 30000,
        'total_amount' => 90000,
    ]);
    $partialOrder = GameServiceOrder::factory()->for($price, 'price')->create([
        'collaborator_unit_cost' => 12345,
    ]);
    $missingPriceOrder = GameServiceOrder::factory()->create();

    $this->artisan('game-service-orders:backfill-settlements')
        ->expectsOutputToContain('Đây là chế độ kiểm tra')
        ->assertSuccessful();

    expect($legacyOrder->refresh()->net_profit)->toBeNull();

    $this->artisan('game-service-orders:backfill-settlements', ['--apply' => true])
        ->assertSuccessful();

    expect($legacyOrder->refresh()->collaborator_unit_cost)->toBe(20000)
        ->and($legacyOrder->collaborator_total_cost)->toBe(60000)
        ->and($legacyOrder->gross_profit)->toBe(30000)
        ->and($legacyOrder->estimated_tax)->toBe(1350)
        ->and($legacyOrder->net_profit)->toBe(28650)
        ->and($partialOrder->refresh()->collaborator_unit_cost)->toBe(12345)
        ->and($partialOrder->net_profit)->toBeNull()
        ->and($missingPriceOrder->refresh()->net_profit)->toBeNull();

    $this->actingAs($admin)
        ->getJson('/api/admin-api/game-service-orders?search='.$legacyOrder->code)
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.collaborator_total_cost', 60000)
        ->assertJsonPath('data.data.0.net_profit', 28650);

    $this->artisan('game-service-orders:backfill-settlements', ['--apply' => true])
        ->assertSuccessful();

    expect($legacyOrder->refresh()->net_profit)->toBe(28650);
});

test('order chat is private to its owner assigned collaborator and admins can intervene', function (): void {
    config(['tenancy.enabled' => false]);
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $collaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $otherCollaborator = User::factory()->create(['role' => User::ROLE_COLLABORATOR]);
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create([
        'image' => '/storage/uploads/games/ngoc-rong-online.webp',
    ]);
    $order = GameServiceOrder::factory()->for($owner)->create([
        'collaborator_id' => $collaborator->id,
        'game_id' => $game->id,
        'status' => 'processing',
    ]);
    $foreignOrder = GameServiceOrder::factory()->create([
        'collaborator_id' => $otherCollaborator->id,
        'status' => 'processing',
    ]);
    $unassignedOrder = GameServiceOrder::factory()->create(['collaborator_id' => null]);
    $collaboratorHeaders = gameServiceSecondaryHeaders($collaborator);

    $this->actingAs($owner)
        ->get(route('account.game-service-orders.chat', $order))
        ->assertSuccessful()
        ->assertSee('data-order-chat-heading', false)
        ->assertSee('/storage/uploads/games/ngoc-rong-online.webp', false);

    $fallbackOrder = GameServiceOrder::factory()->for($owner)->create([
        'game_id' => null,
        'game_name' => 'Ngọc Rồng Online',
        'service_name' => 'Săn đệ tử',
    ]);

    $this->actingAs($owner)
        ->get(route('account.game-service-orders.chat', $fallbackOrder))
        ->assertSuccessful()
        ->assertSee('NG');

    $this->actingAs($owner)
        ->postJson("/api/client/game-service-orders/{$order->code}/messages", ['message' => 'Em cần hỏi tiến độ.'])
        ->assertCreated()
        ->assertJsonPath('data.sender_role', GameServiceOrderMessage::ROLE_USER)
        ->assertJsonPath('data.progress', null);

    $this->actingAs($collaborator)
        ->postJson(
            "/api/client/affiliate/game-service-orders/{$order->code}/messages",
            ['message' => 'Mình đang xử lý nhé.'],
            $collaboratorHeaders,
        )
        ->assertCreated()
        ->assertJsonPath('data.sender_role', GameServiceOrderMessage::ROLE_COLLABORATOR);

    $this->actingAs($admin)
        ->postJson("/api/admin-api/game-service-order-chats/{$order->code}/messages", ['message' => 'Admin đang theo dõi đơn này.'])
        ->assertCreated()
        ->assertJsonPath('data.sender_role', GameServiceOrderMessage::ROLE_ADMIN);

    $this->actingAs($owner)
        ->getJson("/api/client/game-service-orders/{$order->code}/messages")
        ->assertSuccessful()
        ->assertJsonCount(3, 'data.messages')
        ->assertJsonMissingPath('data.order.collaborator_total_cost')
        ->assertJsonMissingPath('data.order.payload');

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-order-chats', $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', $order->code)
        ->assertJsonPath('data.0.messages_count', 3)
        ->assertJsonPath('data.0.last_message.sender_role', GameServiceOrderMessage::ROLE_ADMIN)
        ->assertJsonMissing(['code' => $foreignOrder->code])
        ->assertJsonMissing(['code' => $unassignedOrder->code]);

    $this->actingAs($outsider)
        ->getJson("/api/client/game-service-orders/{$order->code}/messages")
        ->assertForbidden();

    $collaborator->update(['role' => User::ROLE_USER]);

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$order->code}/messages")
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-order-chats')
        ->assertForbidden();

    $collaborator->update(['role' => User::ROLE_COLLABORATOR]);

    $order->update(['collaborator_id' => null]);

    $this->actingAs($collaborator)
        ->postJson(
            "/api/client/affiliate/game-service-orders/{$order->code}/messages",
            ['message' => 'Không còn quyền.'],
            $collaboratorHeaders,
        )
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-order-chats', $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonCount(0, 'data');
});
