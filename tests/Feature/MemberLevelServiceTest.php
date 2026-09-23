<?php

use App\Enums\PaymentMethod;
use App\Features\Topup\Services\OrderService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\MemberLevel;
use App\Models\MemberLevelAccount;
use App\Models\MemberLevelOrderCredit;
use App\Models\MemberLevelPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('ignores legacy member level pricing when resolving current package prices', function (): void {
    $level = MemberLevel::factory()->create([
        'code' => 'legacy-agency',
        'rank' => 10,
        'default_discount_bps' => 2_000,
    ]);
    $user = User::factory()->create();
    MemberLevelAccount::factory()->for($user)->create([
        'manual_level_id' => $level->id,
        'manual_level_expires_at' => now()->addMonth(),
    ]);
    $package = TopupPackage::factory()->create([
        'provider_price' => 80_000,
        'price' => 100_000,
        'original_price' => 110_000,
    ]);
    MemberLevelPackagePrice::factory()->create([
        'member_level_id' => $level->id,
        'topup_package_id' => $package->id,
        'pricing_mode' => 'fixed',
        'fixed_price' => 82_000,
    ]);

    $price = app(TopupPackagePricingService::class)->resolve($package);

    expect($price['retail_price'])->toBe(100_000)
        ->and($price['final_price'])->toBe(100_000)
        ->and($price['tenant_pricing_mode'])->toBe('base_price');
});

it('creates new orders without member level pricing snapshots or credits', function (): void {
    Mail::fake();
    Queue::fake();

    $level = MemberLevel::factory()->create([
        'code' => 'legacy-level',
        'rank' => 5,
        'default_discount_bps' => 1_000,
    ]);
    $user = User::factory()->create();
    $user->wallet()->update(['balance' => 1_000_000]);
    MemberLevelAccount::factory()->for($user)->create([
        'manual_level_id' => $level->id,
        'manual_level_expires_at' => now()->addMonth(),
    ]);
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'game_server_id' => $server->id,
        'provider_price' => 80_000,
        'price' => 100_000,
        'original_price' => 110_000,
    ]);

    $order = app(OrderService::class)->create([
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 2,
        'recipient_fields' => ['game_account' => 'legacy-level-player', 'character_name' => ''],
        'payment_method' => PaymentMethod::Wallet->value,
    ], $user, '127.0.0.1', 'Pest');

    expect($order->member_level_id)->toBeNull()
        ->and($order->member_level_name)->toBeNull()
        ->and($order->member_level_discount_bps)->toBeNull()
        ->and((int) $order->retail_unit_price)->toBe(100_000)
        ->and((int) $order->sale_unit_price)->toBe(100_000)
        ->and((int) $order->member_level_discount_amount)->toBe(0)
        ->and((int) $order->total_amount)->toBe(200_000)
        ->and(MemberLevelOrderCredit::query()->count())->toBe(0);
});
