<?php

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\Game;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Event;
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
        'payload' => ['character_name' => 'BiMatTruocKhiNhan', 'password' => 'secret'],
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.orders.pending', 2)
        ->assertJsonPath('data.orders.processing', 1)
        ->assertJsonPath('data.orders.total', 3)
        ->assertJsonPath('data.revenue.order_revenue', 75000)
        ->assertJsonPath('data.revenue.held', 75000);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending&search='.$pending->code)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.code', $pending->code)
        ->assertJsonPath('data.data.0.status', 'pending')
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.payload_locked', true)
        ->assertJsonPath('data.data.0.can_claim', true)
        ->assertJsonPath('data.data.0.can_chat', true);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending&search='.$availablePending->code)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.code', $availablePending->code)
        ->assertJsonPath('data.data.0.payload', [])
        ->assertJsonPath('data.data.0.can_claim', true)
        ->assertJsonPath('data.data.0.can_chat', false);
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

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/submit", [], $collaboratorHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'review');

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$foreignOrder->code}/start", [], $collaboratorHeaders)
        ->assertForbidden();

    expect($order->refresh()->status)->toBe('review')
        ->and($order->processing_at)->not->toBeNull()
        ->and($availableOrder->refresh()->collaborator_id)->toBe($collaborator->id)
        ->and($availableOrder->status)->toBe('processing')
        ->and($foreignOrder->refresh()->status)->toBe('pending');
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

test('admin completion settles collaborator income once', function (): void {
    $collaborator = activeCollaborator();
    $admin = User::factory()->create(['tenant_id' => $collaborator->tenant_id, 'role' => 'admin']);
    $order = GameServiceOrder::factory()->create([
        'collaborator_id' => $collaborator->id,
        'status' => 'review',
        'collaborator_total_cost' => 42000,
    ]);

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", ['status' => 'completed'])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'completed');

    $this->actingAs($admin)
        ->patchJson("/api/admin-api/game-service-orders/{$order->code}", ['status' => 'completed'])
        ->assertSuccessful();

    $wallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_COLLABORATOR)
        ->firstOrFail();
    $affiliateBalance = (int) Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $collaborator->id)
        ->where('type', Wallet::TYPE_AFFILIATE)
        ->value('balance');

    expect((int) $wallet->balance)->toBe(42000)
        ->and($affiliateBalance)->toBe(0)
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
