<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-20 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('it expires unpaid wallet deposits and game orders after eight hours', function (): void {
    $user = User::factory()->create();
    $expiredDeposit = createDepositRequest($user, 'DEP-EXPIRE', now()->subHours(8)->subSecond());
    $recentDeposit = createDepositRequest($user, 'DEP-RECENT', now()->subHours(8)->addSecond());
    $paidDeposit = createDepositRequest($user, 'DEP-PAID', now()->subHours(9), 'success');

    $expiredOrder = Order::factory()->create(['created_at' => now()->subHours(8)->subSecond()]);
    $recentOrder = Order::factory()->create(['created_at' => now()->subHours(8)->addSecond()]);
    $paidOrder = Order::factory()->create([
        'payment_status' => PaymentStatus::Paid,
        'created_at' => now()->subHours(9),
    ]);
    $linkedPayment = createDepositRequest(
        $user,
        'TOPUP-EXPIRE',
        now()->subHours(8)->subSecond(),
        order: $expiredOrder,
    );

    $this->artisan('orders:expire-unpaid')
        ->expectsOutputToContain('Expired 1 wallet deposit(s) and 1 game order(s)')
        ->assertSuccessful();

    expect($expiredDeposit->refresh()->status)->toBe('cancelled')
        ->and($expiredDeposit->expired_at?->equalTo(now()))->toBeTrue()
        ->and($expiredDeposit->raw_data['cancel_reason'])->toBe('expired')
        ->and($expiredOrder->refresh()->payment_status)->toBe(PaymentStatus::Expired)
        ->and($expiredOrder->expired_at?->equalTo(now()))->toBeTrue()
        ->and($linkedPayment->refresh()->status)->toBe('cancelled')
        ->and($linkedPayment->expired_at?->equalTo(now()))->toBeTrue()
        ->and($recentDeposit->refresh()->status)->toBe('pending')
        ->and($recentDeposit->expired_at)->toBeNull()
        ->and($paidDeposit->refresh()->status)->toBe('success')
        ->and($recentOrder->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and($paidOrder->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('it deletes records that have been expired for more than twenty four hours', function (): void {
    $user = User::factory()->create();
    $staleExpiredAt = now()->subHours(24)->subSecond();
    $recentExpiredAt = now()->subHours(24)->addSecond();

    $staleDeposit = createDepositRequest($user, 'DEP-STALE', now()->subDays(2), 'cancelled', expiredAt: $staleExpiredAt);
    $recentDeposit = createDepositRequest($user, 'DEP-KEEP', now()->subDays(2), 'cancelled', expiredAt: $recentExpiredAt);

    $staleOrder = Order::factory()->create([
        'payment_status' => PaymentStatus::Expired,
        'expired_at' => $staleExpiredAt,
        'created_at' => now()->subDays(2),
    ]);
    $recentOrder = Order::factory()->create([
        'payment_status' => PaymentStatus::Expired,
        'expired_at' => $recentExpiredAt,
        'created_at' => now()->subDays(2),
    ]);
    $staleLinkedPayment = createDepositRequest(
        $user,
        'TOPUP-STALE',
        now()->subDays(2),
        'cancelled',
        $staleOrder,
        $staleExpiredAt,
    );

    $this->artisan('orders:expire-unpaid')
        ->expectsOutputToContain('deleted 1 wallet deposit(s) and 1 game order(s)')
        ->assertSuccessful();

    $this->assertModelMissing($staleDeposit);
    $this->assertModelMissing($staleOrder);
    $this->assertModelMissing($staleLinkedPayment);
    $this->assertModelExists($recentDeposit);
    $this->assertModelExists($recentOrder);
});

test('it schedules unpaid order expiration every ten minutes', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'orders:expire-unpaid'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/10 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

function createDepositRequest(
    User $user,
    string $code,
    Carbon $createdAt,
    string $status = 'pending',
    ?Order $order = null,
    ?Carbon $expiredAt = null,
): PaymentTransaction {
    $transaction = PaymentTransaction::query()->create([
        'user_id' => $user->id,
        'order_id' => $order?->id,
        'transaction_code' => $code,
        'amount' => 100000,
        'status' => $status,
        'expired_at' => $expiredAt,
        'raw_data' => $status === 'cancelled' ? ['cancel_reason' => 'expired'] : [],
    ]);

    $transaction->forceFill(['created_at' => $createdAt])->save();

    return $transaction;
}
