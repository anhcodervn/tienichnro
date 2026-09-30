<?php

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderMessage;
use App\Models\GameServiceOrderProgress;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function activeCollaborator(): User
{
    Event::fake([AffiliateDashboardUpdated::class]);
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $collaborator = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_COLLABORATOR,
    ]);
    AffiliateProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $collaborator->id,
        'status' => 'active',
    ]);

    return $collaborator;
}

function progressPngUpload(string $name = 'progress.png'): UploadedFile
{
    $content = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true,
    );

    return UploadedFile::fake()->createWithContent($name, $content);
}

test('collaborator dashboard includes available pending orders without counting them as personal revenue', function (): void {
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $service = GameService::factory()->for($game)->create();
    $collaborator->allowedGameServices()->attach($service->id);
    $pending = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'game_id' => $game->id,
        'game_name' => $game->name,
        'status' => 'pending',
        'collaborator_total_cost' => 30000,
    ]);
    GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'game_id' => $game->id,
        'game_name' => $game->name,
        'status' => 'processing',
        'collaborator_total_cost' => 45000,
    ]);
    GameServiceOrder::factory()->create([
        'collaborator_id' => $otherCollaborator->id,
        'game_id' => $game->id,
        'game_name' => $game->name,
        'status' => 'pending',
        'collaborator_total_cost' => 999000,
    ]);
    $availablePending = GameServiceOrder::factory()->create([
        'collaborator_id' => null,
        'game_id' => $game->id,
        'game_service_id' => $service->id,
        'game_name' => $game->name,
        'status' => 'pending',
        'collaborator_total_cost' => 25000,
        'payload' => [
            'character_name' => 'BiMatTruocKhiNhan',
            'password' => 'secret',
            'note' => 'Ưu tiên hoàn thành trước 20 giờ.',
        ],
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.orders.pending', 2)
        ->assertJsonPath('data.orders.processing', 1)
        ->assertJsonPath('data.orders.total', 3)
        ->assertJsonPath('data.revenue.order_revenue', 75000)
        ->assertJsonPath('data.revenue.held', 0);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending&search='.$pending->code)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.code', $pending->code)
        ->assertJsonPath('data.data.0.status', 'pending')
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true)
        ->assertJsonPath('data.data.0.can_claim', true)
        ->assertJsonPath('data.data.0.can_chat', false);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending&search='.$availablePending->code)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.code', $availablePending->code)
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.can_claim', true)
        ->assertJsonPath('data.data.0.can_chat', false);

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availablePending->code}/preview")
        ->assertSuccessful()
        ->assertJsonPath('data.code', $availablePending->code)
        ->assertJsonPath('data.customer_note', 'Ưu tiên hoàn thành trước 20 giờ.')
        ->assertJsonMissingPath('data.payload')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissing(['character_name' => 'BiMatTruocKhiNhan']);

    $this->actingAs($otherCollaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availablePending->code}/preview")
        ->assertForbidden();
});

test('collaborator can process assigned orders but cannot process another collaborators order', function (): void {
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $service = GameService::factory()->create();
    $collaborator->allowedGameServices()->attach($service->id);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'pending',
        'collaborator_total_cost' => 35000,
    ]);
    $foreignOrder = GameServiceOrder::factory()->create([
        'collaborator_id' => $otherCollaborator->id,
        'status' => 'pending',
        'collaborator_total_cost' => 35000,
    ]);
    $availableOrder = GameServiceOrder::factory()->create([
        'collaborator_id' => null,
        'game_service_id' => $service->id,
        'status' => 'pending',
        'collaborator_total_cost' => 28000,
        'payload' => ['character_name' => 'NhanSauKhiClaim', 'password' => 'secret'],
    ]);
    $collaboratorHeaders = gameServiceSecondaryHeaders($collaborator);
    $otherCollaboratorHeaders = gameServiceSecondaryHeaders($otherCollaborator);

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/messages", $collaboratorHeaders)
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/payload", $collaboratorHeaders)
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/start", [], $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'processing')
        ->assertJsonPath('data.collaborator_id', $collaborator->id);

    $this->actingAs($otherCollaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/start", [], $otherCollaboratorHeaders)
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/messages", $collaboratorHeaders)
        ->assertSuccessful();

    $this->actingAs($collaborator)
        ->getJson("/api/client/affiliate/game-service-orders/{$availableOrder->code}/payload", $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.payload.character_name', 'NhanSauKhiClaim')
        ->assertJsonPath('data.payload.password', 'secret');

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=processing&search='.$availableOrder->code)
        ->assertSuccessful()
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true)
        ->assertJsonPath('data.data.0.can_claim', false)
        ->assertJsonPath('data.data.0.can_chat', true);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/start", [], $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'processing');

    Storage::fake('local');

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Đã hoàn thành nhưng chưa có ảnh.',
        ], $collaboratorHeaders)
        ->assertUnprocessable();

    $this->actingAs($collaborator)
        ->post("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Đã hoàn thành đầy đủ.',
            'image' => progressPngUpload('verification.png'),
        ], $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.order.status', 'review')
        ->assertJsonPath('data.progress.0.type', GameServiceOrderProgress::TYPE_COMPLETION);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$foreignOrder->code}/start", [], $collaboratorHeaders)
        ->assertForbidden();

    expect($order->refresh()->status)->toBe('review')
        ->and($order->processing_at)->not->toBeNull()
        ->and($availableOrder->refresh()->collaborator_id)->toBe($collaborator->id)
        ->and($availableOrder->status)->toBe('processing')
        ->and($foreignOrder->refresh()->status)->toBe('pending');

    $workWallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_COLLABORATOR)
        ->firstOrFail();

    expect((int) $workWallet->work_hold_balance)->toBe(63000)
        ->and(WalletTransaction::query()->withoutGlobalScopes()
            ->where('wallet_id', $workWallet->id)
            ->where('type', 'hold')
            ->count())->toBe(2);
});

test('order progress is private and completion requires a verification image', function (): void {
    Storage::fake('local');
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $owner = User::factory()->create(['tenant_id' => $collaborator->tenant_id]);
    $outsider = User::factory()->create(['tenant_id' => $collaborator->tenant_id]);
    $admin = User::factory()->create(['tenant_id' => $collaborator->tenant_id, 'role' => User::ROLE_ADMIN]);
    $order = GameServiceOrder::factory()->for($owner)->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'processing',
    ]);

    $this->actingAs($collaborator)
        ->post("/api/client/affiliate/game-service-orders/{$order->code}/progress", [
            'description' => 'Đã thực hiện khoảng 50%.',
            'image' => progressPngUpload(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.progress.type', GameServiceOrderProgress::TYPE_PROGRESS)
        ->assertJsonPath('data.progress.description', 'Đã thực hiện khoảng 50%.');

    $progress = GameServiceOrderProgress::query()
        ->where('game_service_order_id', $order->id)
        ->where('type', GameServiceOrderProgress::TYPE_PROGRESS)
        ->firstOrFail();
    $progressMessage = GameServiceOrderMessage::query()
        ->where('game_service_order_progress_id', $progress->id)
        ->firstOrFail();

    expect($progressMessage->game_service_order_id)->toBe($order->id)
        ->and($progressMessage->sender_id)->toBe($collaborator->id)
        ->and($progressMessage->sender_role)->toBe(GameServiceOrderMessage::ROLE_COLLABORATOR)
        ->and($progressMessage->message)->toBe('Đã thực hiện khoảng 50%.');

    $progressImageUrl = route('account.game-service-orders.progress.image', [
        'gameServiceOrder' => $order,
        'gameServiceOrderProgress' => $progress,
    ]);

    $this->actingAs($owner)
        ->getJson("/api/client/game-service-orders/{$order->code}/messages")
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.message', 'Đã thực hiện khoảng 50%.')
        ->assertJsonPath('data.messages.0.progress.id', $progress->id)
        ->assertJsonPath('data.messages.0.progress.image_url', $progressImageUrl);

    $this->actingAs($otherCollaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/progress", [
            'description' => 'Không có quyền.',
        ])
        ->assertForbidden();

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Thiếu ảnh xác minh.',
        ])
        ->assertUnprocessable();

    expect($order->refresh()->status)->toBe('processing');

    $this->actingAs($collaborator)
        ->post("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Đã hoàn thành và có ảnh xác minh.',
            'image' => progressPngUpload('complete.png'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.order.status', 'review')
        ->assertJsonCount(2, 'data.progress');

    $completion = GameServiceOrderProgress::query()
        ->where('game_service_order_id', $order->id)
        ->where('type', GameServiceOrderProgress::TYPE_COMPLETION)
        ->firstOrFail();

    Storage::disk('local')->assertExists((string) $completion->image_path);

    $this->actingAs($owner)
        ->getJson("/api/client/game-service-orders/{$order->code}/progress")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.progress');

    $this->actingAs($admin)
        ->getJson("/api/admin-api/game-service-orders/{$order->code}/progress")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.progress');

    $this->actingAs($outsider)
        ->getJson("/api/client/game-service-orders/{$order->code}/progress")
        ->assertForbidden();

    $imageUrl = route('account.game-service-orders.progress.image', [
        'gameServiceOrder' => $order,
        'gameServiceOrderProgress' => $completion,
    ]);

    $this->actingAs($owner)->get($imageUrl)->assertSuccessful()->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs($outsider)->get($imageUrl)->assertForbidden();

    $this->actingAs($collaborator)
        ->post("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Không thể gửi hoàn thành lần hai.',
            'image' => progressPngUpload('duplicate.png'),
        ])
        ->assertUnprocessable();

    expect(GameServiceOrderProgress::query()->where('game_service_order_id', $order->id)->count())->toBe(2)
        ->and(GameServiceOrderMessage::query()->where('game_service_order_id', $order->id)->count())->toBe(1);
});

test('work announcements and affiliate announcements keep separate feeds and read markers', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $tenant->id, 'is_enabled' => true]);
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $workAnnouncement = AffiliateAnnouncement::factory()->forCollaborators()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Thông báo công việc',
    ]);
    $affiliateAnnouncement = AffiliateAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Thông báo Affiliate',
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-announcements')
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 1)
        ->assertJsonPath('data.announcements.0.id', $workAnnouncement->id)
        ->assertJsonPath('data.announcements.0.is_read', false);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/home')
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 1)
        ->assertJsonPath('data.announcements.0.id', $affiliateAnnouncement->id);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-announcements/{$workAnnouncement->id}/read")
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 0);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-announcements')
        ->assertJsonPath('data.announcements.0.is_read', true)
        ->assertJsonPath('data.unread_count', 0);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/home')
        ->assertJsonPath('data.announcements.0.is_read', false)
        ->assertJsonPath('data.unread_count', 1);

    $this->actingAs($otherCollaborator)
        ->getJson('/api/client/affiliate/game-service-announcements')
        ->assertJsonPath('data.unread_count', 1);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/announcements/{$workAnnouncement->id}/read")
        ->assertNotFound();
});

test('admin completion holds collaborator income for three days then releases it once', function (): void {
    Storage::fake('local');
    $collaborator = activeCollaborator();
    $admin = User::factory()->create(['tenant_id' => $collaborator->tenant_id, 'role' => 'admin']);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'review',
        'collaborator_total_cost' => 42000,
    ]);
    $proofPath = "game-service-orders/{$order->code}/completion/proof.webp";
    Storage::disk('local')->put($proofPath, 'proof');
    GameServiceOrderProgress::factory()->create([
        'game_service_order_id' => $order->id,
        'user_id' => $collaborator->id,
        'type' => GameServiceOrderProgress::TYPE_COMPLETION,
        'image_path' => $proofPath,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/approve-completion")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/approve-completion")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $wallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_COLLABORATOR)
        ->firstOrFail();
    $affiliateBalance = (int) Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_AFFILIATE)
        ->value('balance');

    expect((int) $wallet->balance)->toBe(0)
        ->and((int) $wallet->work_hold_balance)->toBe(42000)
        ->and($affiliateBalance)->toBe(0)
        ->and($order->refresh()->collaborator_settlement_amount)->toBeNull()
        ->and($order->collaborator_available_at?->equalTo($order->completed_at?->copy()->addDays(3)))->toBeTrue()
        ->and($order->collaborator_settled_at)->toBeNull();

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-wallet-history')
        ->assertSuccessful()
        ->assertJsonPath('data.wallet.work_hold_balance', 42000)
        ->assertJsonPath('data.data.0.event', 'game_service_order_claimed')
        ->assertJsonPath('data.data.0.order_code', $order->code);

    $this->artisan('game-service-orders:release-collaborator-funds')->assertSuccessful();
    expect((int) $wallet->refresh()->balance)->toBe(0);

    $this->travel(3)->days();
    $this->artisan('game-service-orders:release-collaborator-funds')->assertSuccessful();
    $this->artisan('game-service-orders:release-collaborator-funds')->assertSuccessful();

    expect((int) $wallet->refresh()->balance)->toBe(42000)
        ->and((int) $wallet->work_hold_balance)->toBe(0)
        ->and($order->refresh()->collaborator_settlement_amount)->toBe(42000)
        ->and($order->collaborator_settled_at)->not->toBeNull();
});

test('collaborator finance and withdrawals use a separate wallet from affiliate commissions', function (): void {
    $collaborator = activeCollaborator();
    AffiliateProgram::factory()->create([
        'tenant_id' => $collaborator->tenant_id,
        'is_enabled' => true,
    ]);
    Wallet::query()->create([
        'tenant_id' => $collaborator->tenant_id,
        'user_id' => $collaborator->id,
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 70000,
    ]);
    Wallet::query()->create([
        'tenant_id' => $collaborator->tenant_id,
        'user_id' => $collaborator->id,
        'type' => Wallet::TYPE_COLLABORATOR,
        'balance' => 150000,
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-finance')
        ->assertSuccessful()
        ->assertJsonPath('data.wallet.balance', 150000)
        ->assertJsonPath('data.wallet.hold_balance', 0)
        ->assertJsonPath('data.minimum_withdrawal', 100000);

    $this->actingAs($collaborator)
        ->postJson('/api/client/affiliate/game-service-withdrawals', [
            'amount' => 100000,
            'idempotency_key' => (string) Str::uuid(),
        ], gameServiceSecondaryHeaders($collaborator))
        ->assertCreated();

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate')
        ->assertSuccessful()
        ->assertJsonPath('data.wallets.affiliate.balance', 70000)
        ->assertJsonCount(0, 'data.withdrawals');

    $collaboratorWallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)->where('type', Wallet::TYPE_COLLABORATOR)->firstOrFail();
    $affiliateWallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)->where('type', Wallet::TYPE_AFFILIATE)->firstOrFail();
    $withdrawal = AffiliateWithdrawal::query()->withoutGlobalScopes()->latest('id')->firstOrFail();

    expect((int) $collaboratorWallet->balance)->toBe(50000)
        ->and((int) $collaboratorWallet->hold_balance)->toBe(100000)
        ->and((int) $affiliateWallet->balance)->toBe(70000)
        ->and($withdrawal->wallet_type)->toBe(AffiliateWithdrawal::WALLET_COLLABORATOR);
});

test('admin can work as a collaborator and withdraw work income from the dashboard', function (): void {
    Storage::fake('local');
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $service = GameService::factory()->create();
    $order = GameServiceOrder::factory()->create([
        'game_service_id' => $service->id,
        'collaborator_id' => null,
        'status' => 'pending',
        'collaborator_total_cost' => 125000,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.orders.pending', 1);

    $this->actingAs($admin)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/start")
        ->assertSuccessful()
        ->assertJsonPath('data.collaborator_id', $admin->id)
        ->assertJsonPath('data.status', 'processing');

    $this->actingAs($admin)
        ->post("/api/client/affiliate/game-service-orders/{$order->code}/submit", [
            'description' => 'Admin trực tiếp hoàn thành đơn với vai trò cộng tác viên.',
            'image' => progressPngUpload('admin-completion.png'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.order.status', 'review');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}/approve-completion")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $workWallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $admin->id)
        ->where('type', Wallet::TYPE_COLLABORATOR)
        ->firstOrFail();

    expect((int) $workWallet->balance)->toBe(0)
        ->and((int) $workWallet->work_hold_balance)->toBe(125000);

    $this->travel(3)->days();
    $this->artisan('game-service-orders:release-collaborator-funds')->assertSuccessful();

    expect((int) $workWallet->refresh()->balance)->toBe(125000)
        ->and((int) $workWallet->work_hold_balance)->toBe(0);

    $secondaryHeaders = gameServiceSecondaryHeaders($admin);
    $this->actingAs($admin)
        ->putJson('/api/client/affiliate/game-service-payout-account', [
            'bank_name' => 'Vietcombank',
            'bank_account_name' => 'ADMIN NAPCAROT',
            'bank_account_number' => '0123456789',
        ], $secondaryHeaders)
        ->assertSuccessful();

    $this->actingAs($admin)
        ->postJson('/api/client/affiliate/game-service-withdrawals', [
            'amount' => 100000,
            'idempotency_key' => (string) Str::uuid(),
        ], $secondaryHeaders)
        ->assertCreated();

    expect((int) $workWallet->refresh()->balance)->toBe(25000)
        ->and((int) $workWallet->hold_balance)->toBe(100000);
});
