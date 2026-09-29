<?php

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\Game;
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
    $collaborator = User::factory()->create(['tenant_id' => $tenant->id]);
    AffiliateProfile::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $collaborator->id,
        'status' => 'active',
    ]);

    return $collaborator;
}

test('collaborator dashboard summarizes only assigned orders and filters pending orders', function (): void {
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
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

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-dashboard')
        ->assertSuccessful()
        ->assertJsonPath('data.orders.pending', 1)
        ->assertJsonPath('data.orders.processing', 1)
        ->assertJsonPath('data.orders.total', 2)
        ->assertJsonPath('data.revenue.order_revenue', 75000)
        ->assertJsonPath('data.revenue.held', 75000);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/game-service-orders?status=pending&search='.$pending->code)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.code', $pending->code)
        ->assertJsonPath('data.data.0.status', 'pending')
        ->assertJsonPath('data.data.0.payload.character_name', $pending->payload['character_name']);
});

test('collaborator can process assigned orders but cannot process another collaborators order', function (): void {
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
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

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/start")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'processing');

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$order->code}/submit")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'review');

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/game-service-orders/{$foreignOrder->code}/start")
        ->assertForbidden();

    expect($order->refresh()->status)->toBe('review')
        ->and($order->processing_at)->not->toBeNull()
        ->and($foreignOrder->refresh()->status)->toBe('pending');
});

test('opening an admin announcement clears its unread marker only for that collaborator', function (): void {
    $tenant = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $tenant->id, 'is_enabled' => true]);
    $collaborator = activeCollaborator();
    $otherCollaborator = activeCollaborator();
    $announcement = AffiliateAnnouncement::factory()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Thông báo mới',
    ]);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/home')
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 1)
        ->assertJsonPath('data.announcements.0.is_read', false);

    $this->actingAs($collaborator)
        ->postJson("/api/client/affiliate/announcements/{$announcement->id}/read")
        ->assertSuccessful()
        ->assertJsonPath('data.unread_count', 0);

    $this->actingAs($collaborator)
        ->getJson('/api/client/affiliate/home')
        ->assertJsonPath('data.announcements.0.is_read', true);

    $this->actingAs($otherCollaborator)
        ->getJson('/api/client/affiliate/home')
        ->assertJsonPath('data.unread_count', 1);
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
        ])
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
