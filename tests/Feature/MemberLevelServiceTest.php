<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\MemberLevel\Services\MemberLevelPriceService;
use App\Features\MemberLevel\Services\MemberLevelService;
use App\Features\Topup\Services\OrderPricingService;
use App\Features\Topup\Services\OrderService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\MemberLevel;
use App\Models\MemberLevelAccount;
use App\Models\MemberLevelOrderCredit;
use App\Models\MemberLevelPackagePrice;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function createMemberLevels(): array
{
    MemberLevel::query()->delete();

    return [
        MemberLevel::factory()->create(['code' => 'member', 'name' => 'Thành viên', 'rank' => 0, 'lifetime_threshold' => 0, 'maintenance_amount' => 0]),
        MemberLevel::factory()->create(['code' => 'level-1', 'name' => 'Level 1', 'rank' => 1, 'lifetime_threshold' => 100000, 'maintenance_amount' => 10000]),
        MemberLevel::factory()->create(['code' => 'level-2', 'name' => 'Level 2', 'rank' => 2, 'lifetime_threshold' => 1000000, 'maintenance_amount' => 100000]),
        MemberLevel::factory()->create(['code' => 'level-3', 'name' => 'Level 3', 'rank' => 3, 'lifetime_threshold' => 1500000, 'maintenance_amount' => 300000]),
    ];
}

it('unlocks levels permanently and only reduces the effective benefit by one level', function () {
    [, , $levelTwo, $levelThree] = createMemberLevels();
    $user = User::factory()->create();

    Order::factory()->for($user)->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'total_amount' => 1500000,
        'completed_at' => now()->subDays(40),
    ]);

    $service = app(MemberLevelService::class);
    $inactiveStatus = $service->status($user);

    expect($inactiveStatus['unlocked_level']['id'])->toBe($levelThree->id)
        ->and($inactiveStatus['effective_level']['id'])->toBe($levelTwo->id)
        ->and($inactiveStatus['is_temporarily_downgraded'])->toBeTrue()
        ->and(MemberLevelOrderCredit::query()->count())->toBe(1);

    $levelThree->update(['lifetime_threshold' => 10000000]);
    expect($service->status($user)['unlocked_level']['id'])->toBe($levelThree->id);

    Order::factory()->for($user)->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'total_amount' => 300000,
        'completed_at' => now()->subDay(),
    ]);

    $maintainedStatus = $service->status($user);
    $service->status($user);

    expect($maintainedStatus['effective_level']['id'])->toBe($levelThree->id)
        ->and($maintainedStatus['rolling_completed_amount'])->toBe(300000)
        ->and($maintainedStatus['maintenance_remaining_amount'])->toBe(0)
        ->and(MemberLevelOrderCredit::query()->count())->toBe(2);
});

it('restores a level at the exact rolling maintenance boundary', function () {
    $this->travelTo(now()->startOfSecond());
    [, , , $levelThree] = createMemberLevels();
    $user = User::factory()->create();
    MemberLevelAccount::factory()->for($user)->create([
        'earned_level_id' => $levelThree->id,
        'lifetime_completed_amount' => 1500000,
    ]);
    MemberLevelOrderCredit::factory()->for($user)->create([
        'amount' => 300000,
        'occurred_at' => now()->subDays(31),
    ]);

    $status = app(MemberLevelService::class)->status($user, false);

    expect($status['effective_level']['id'])->toBe($levelThree->id)
        ->and($status['is_maintained'])->toBeTrue();
});

it('applies level pricing overrides and protects the minimum margin', function () {
    [, $levelOne] = createMemberLevels();
    $levelOne->update(['default_discount_bps' => 1000, 'minimum_profit' => 50]);
    $user = User::factory()->create();
    MemberLevelAccount::factory()->for($user)->create([
        'manual_level_id' => $levelOne->id,
        'manual_level_expires_at' => now()->addMonth(),
    ]);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_price' => 800,
        'price' => 1000,
        'original_price' => 1200,
    ]);

    $defaultPrice = app(MemberLevelPriceService::class)->resolve($package, $user);
    expect($defaultPrice['final_price'])->toBe(900)
        ->and($defaultPrice['discount_amount'])->toBe(100);

    MemberLevelPackagePrice::factory()->create([
        'member_level_id' => $levelOne->id,
        'topup_package_id' => $package->id,
        'pricing_mode' => 'fixed',
        'discount_basis_points' => null,
        'fixed_price' => 820,
        'minimum_profit' => 100,
    ]);

    $quote = app(OrderPricingService::class)->quote(
        gameId: $game->id,
        serverId: $server->id,
        packageId: $package->id,
        quantity: 2,
        user: $user,
    );

    expect($quote['retail_unit_price'])->toBe(1000)
        ->and($quote['sale_unit_price'])->toBe(900)
        ->and($quote['member_level_discount_amount'])->toBe(200)
        ->and($quote['total_amount'])->toBe(1800)
        ->and($quote['gross_profit'])->toBe(200);
});

it('does not credit guest or incomplete orders', function () {
    createMemberLevels();
    $user = User::factory()->create();
    $pendingOrder = Order::factory()->for($user)->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
    ]);
    $guestOrder = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'completed_at' => now(),
    ]);

    $service = app(MemberLevelService::class);
    $service->synchronizeOrder($pendingOrder);
    $service->synchronizeOrder($guestOrder);

    expect(MemberLevelOrderCredit::query()->count())->toBe(0);
});

it('snapshots the applied member level price on a new order', function () {
    Mail::fake();
    Queue::fake();
    [, $levelOne] = createMemberLevels();
    $levelOne->update(['default_discount_bps' => 500, 'minimum_profit' => 1000]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 1000000]);
    MemberLevelAccount::factory()->for($user)->create([
        'manual_level_id' => $levelOne->id,
        'manual_level_expires_at' => now()->addMonth(),
    ]);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_price' => 80000,
        'price' => 100000,
        'original_price' => 110000,
    ]);

    $order = app(OrderService::class)->create([
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 2,
        'recipient_fields' => ['game_account' => 'agency-player', 'game_character' => ''],
        'payment_method' => PaymentMethod::Wallet->value,
    ], $user, '127.0.0.1', 'Pest');

    expect($order->member_level_id)->toBe($levelOne->id)
        ->and($order->member_level_name)->toBe('Level 1')
        ->and($order->member_level_discount_bps)->toBe(500)
        ->and((int) $order->retail_unit_price)->toBe(100000)
        ->and((int) $order->sale_unit_price)->toBe(95000)
        ->and((int) $order->member_level_discount_amount)->toBe(10000)
        ->and((int) $order->total_amount)->toBe(190000);
});
