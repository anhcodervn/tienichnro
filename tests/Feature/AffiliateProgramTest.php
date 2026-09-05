<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Features\Affiliate\Services\AffiliateCommissionService;
use App\Features\Topup\Services\OrderStatusService;
use App\Models\AffiliateCommission;
use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Utils\Site;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

test('registration captures a valid referral only when the site program is enabled', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    $referrer = User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'AFFMAIN01']);

    $this->get('http://napcarot.com/dang-ky?ref=AFFMAIN01')->assertSuccessful();

    $this->postJson('http://napcarot.com/dang-ky', [
        'username' => 'affiliate_buyer',
        'email' => 'affiliate-buyer@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'accept_terms' => true,
    ])->assertCreated();

    $buyer = User::query()->withoutGlobalScopes()->where('username', 'affiliate_buyer')->firstOrFail();

    expect($buyer->tenant_id)->toBe($main->id)
        ->and($buyer->referred_by)->toBe($referrer->id)
        ->and($buyer->referral_code)->toBeString()->toHaveLength(10)
        ->and(AffiliateProfile::query()->withoutGlobalScopes()->where('user_id', $referrer->id)->exists())->toBeTrue();
});

test('registration ignores referrals when the site program is disabled', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => false]);
    User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'AFFOFF001']);

    $this->get('http://napcarot.com/dang-ky?ref=AFFOFF001')->assertSuccessful();
    $this->postJson('http://napcarot.com/dang-ky', [
        'username' => 'unreferred_buyer',
        'email' => 'unreferred-buyer@example.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'accept_terms' => true,
    ])->assertCreated();

    expect(User::query()->withoutGlobalScopes()->where('username', 'unreferred_buyer')->value('referred_by'))->toBeNull();
});

test('fixed commission is held for exactly seven days and released idempotently', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'commission_type' => AffiliatePackageRate::TYPE_FIXED,
        'fixed_amount' => 2500,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
        'quantity' => 2,
        'total_amount' => 180000,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
    ]);

    $completedAt = now()->startOfSecond();
    $this->travelTo($completedAt);
    Site::for($main, function () use ($order): void {
        app(AffiliateCommissionService::class)->snapshot($order);
        app(OrderStatusService::class)->transition($order, OrderStatus::Completed);
    });

    $commission = AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    expect($commission->amount)->toBe(5000)
        ->and($commission->status)->toBe(AffiliateCommission::STATUS_PENDING)
        ->and($commission->earned_at?->equalTo($completedAt))->toBeTrue()
        ->and($commission->available_at?->equalTo($completedAt->copy()->addDays(7)))->toBeTrue();

    $this->travelTo($commission->available_at->copy()->subSecond());
    expect(app(AffiliateCommissionService::class)->releaseDue())->toBe(0);

    $this->travelTo($commission->available_at);
    $commission->update(['is_flagged' => true, 'hold_reason' => 'Manual review']);
    expect(app(AffiliateCommissionService::class)->releaseDue())->toBe(0);
    $commission->update(['is_flagged' => false, 'hold_reason' => null]);
    expect(app(AffiliateCommissionService::class)->releaseDue())->toBe(1)
        ->and(app(AffiliateCommissionService::class)->releaseDue())->toBe(0);

    $wallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $referrer->id)->where('type', Wallet::TYPE_AFFILIATE)->firstOrFail();
    expect((int) $wallet->balance)->toBe(5000)
        ->and(WalletTransaction::query()->withoutGlobalScopes()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', AffiliateCommission::class)
            ->count())->toBe(1);

    $this->travelBack();
});

test('percentage commission uses the paid order total snapshot', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'commission_type' => AffiliatePackageRate::TYPE_PERCENTAGE,
        'fixed_amount' => null,
        'percentage_basis_points' => 500,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
        'total_amount' => 99000,
    ]);

    $commission = Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($order));

    expect($commission)->toBeInstanceOf(AffiliateCommission::class)
        ->and($commission->commission_type)->toBe(AffiliatePackageRate::TYPE_PERCENTAGE)
        ->and($commission->rate_value)->toBe(500)
        ->and($commission->base_amount)->toBe(99000)
        ->and($commission->amount)->toBe(4950)
        ->and(AffiliateProfile::query()->withoutGlobalScopes()->where('user_id', $referrer->id)->exists())->toBeTrue();
});

test('a referral from another site never creates a commission', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $otherSite = Tenant::factory()->create();
    $package = TopupPackage::factory()->create();
    $foreignReferrer = User::factory()->create(['tenant_id' => $otherSite->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $foreignReferrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'fixed_amount' => 3000,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
    ]);

    $commission = Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($order));

    expect($commission)->toBeNull()
        ->and(AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->exists())->toBeFalse()
        ->and(AffiliateProfile::query()->withoutGlobalScopes()->where('user_id', $foreignReferrer->id)->exists())->toBeFalse();
});

test('a refund reverses an available commission only once', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create(['tenant_id' => $main->id, 'topup_package_id' => $package->id, 'fixed_amount' => 3000]);
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Processing,
    ]);

    Site::for($main, function () use ($order): void {
        app(AffiliateCommissionService::class)->snapshot($order);
        app(OrderStatusService::class)->transition($order, OrderStatus::Completed);
    });
    $commission = AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    $this->travelTo($commission->available_at);
    expect(app(AffiliateCommissionService::class)->releaseDue())->toBe(1);

    $order->refresh()->forceFill(['payment_status' => PaymentStatus::Refunded])->save();
    $order->refresh()->forceFill(['payment_status' => PaymentStatus::Refunded])->save();

    $commission->refresh();
    $wallet = Wallet::query()->withoutGlobalScopes()
        ->where('user_id', $referrer->id)->where('type', Wallet::TYPE_AFFILIATE)->firstOrFail();
    expect($commission->status)->toBe(AffiliateCommission::STATUS_REVERSED)
        ->and((int) $wallet->balance)->toBe(0)
        ->and(WalletTransaction::query()->withoutGlobalScopes()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', AffiliateCommission::class)
            ->count())->toBe(2);

    $this->travelBack();
});

test('cancelling an order closes its pending commission', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'fixed_amount' => 3000,
    ]);
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
    ]);

    Site::for($main, function () use ($order): void {
        app(AffiliateCommissionService::class)->snapshot($order);
        app(OrderStatusService::class)->transition($order, OrderStatus::Cancelled);
    });

    $commission = AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    expect($commission->status)->toBe(AffiliateCommission::STATUS_REVERSED)
        ->and($commission->reversal_reason)->toBe('Đơn hàng đã bị hủy.');
});

test('a completed order starts its hold when a late payment is confirmed', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'fixed_amount' => 3000,
    ]);
    $completedAt = now()->startOfSecond();
    $order = Order::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $package->game_id,
        'topup_package_id' => $package->id,
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Completed,
        'completed_at' => $completedAt,
    ]);
    Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($order));

    $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();

    $commission = AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->firstOrFail();
    expect($commission->earned_at?->equalTo($completedAt))->toBeTrue()
        ->and($commission->available_at?->equalTo($completedAt->copy()->addDays(7)))->toBeTrue();
});

test('global affiliate rate applies to every game using the shared global package', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $globalPackage = GlobalTopupPackage::factory()->create();
    $games = Game::factory()->count(2)->create(['package_mode' => 'global']);
    $packages = $games->map(fn (Game $game) => TopupPackage::factory()->create([
        'game_id' => $game->id,
        'global_topup_package_id' => $globalPackage->id,
    ]));
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliateGlobalPackageRate::factory()->create([
        'tenant_id' => $main->id,
        'global_topup_package_id' => $globalPackage->id,
        'fixed_amount' => 2400,
    ]);

    foreach ($packages as $package) {
        $order = Order::factory()->create([
            'tenant_id' => $main->id,
            'user_id' => $buyer->id,
            'game_id' => $package->game_id,
            'topup_package_id' => $package->id,
            'package_source' => 'global',
            'global_topup_package_id' => $globalPackage->id,
            'quantity' => 2,
        ]);

        Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($order));

        expect(AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $order->id)->value('amount'))->toBe(4800);
    }
});

test('package affiliate override wins over global and inactive override suppresses fallback', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $globalPackage = GlobalTopupPackage::factory()->create();
    $game = Game::factory()->create(['package_mode' => 'global']);
    $package = TopupPackage::factory()->create([
        'game_id' => $game->id,
        'global_topup_package_id' => $globalPackage->id,
    ]);
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $buyer = User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliateGlobalPackageRate::factory()->create([
        'tenant_id' => $main->id,
        'global_topup_package_id' => $globalPackage->id,
        'fixed_amount' => 2000,
    ]);
    $packageRate = AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'fixed_amount' => 3500,
    ]);
    $orderAttributes = [
        'tenant_id' => $main->id,
        'user_id' => $buyer->id,
        'game_id' => $game->id,
        'topup_package_id' => $package->id,
        'package_source' => 'global',
        'global_topup_package_id' => $globalPackage->id,
    ];
    $overrideOrder = Order::factory()->create($orderAttributes);

    Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($overrideOrder));
    expect(AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $overrideOrder->id)->value('amount'))->toBe(3500);

    $packageRate->update(['is_active' => false]);
    $disabledOrder = Order::factory()->create($orderAttributes);
    Site::for($main, fn () => app(AffiliateCommissionService::class)->snapshot($disabledOrder));

    expect(AffiliateCommission::query()->withoutGlobalScopes()->where('order_id', $disabledOrder->id)->exists())->toBeFalse();
});
