<?php

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\GameServiceOrder;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Str;

test('guest is redirected to login from game service order history', function (): void {
    $this->get(route('account.game-service-orders.index'))
        ->assertRedirect('/login');
});

test('user only sees their game service orders', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    GameServiceOrder::factory()->create([
        'user_id' => $user->id,
        'code' => 'GSVOWNORDER1',
        'service_name' => 'Săn đệ tử',
    ]);
    GameServiceOrder::factory()->create([
        'user_id' => $otherUser->id,
        'code' => 'GSVOTHER001',
        'service_name' => 'Dịch vụ người khác',
    ]);

    $this->actingAs($user)
        ->get(route('account.game-service-orders.index'))
        ->assertSuccessful()
        ->assertViewIs('client.account.game-service-orders.index')
        ->assertSee('Lịch sử dịch vụ')
        ->assertSee('GSVOWNORDER1')
        ->assertSee('Săn đệ tử')
        ->assertDontSee('GSVOTHER001')
        ->assertDontSee('Dịch vụ người khác');
});

test('owner can cancel a pending paid order and receive an exact wallet refund once', function (): void {
    $user = User::factory()->create();
    $wallet = $user->wallet()->firstOrFail();
    $wallet->forceFill(['balance' => 100000, 'total_spent' => 0])->save();
    $order = GameServiceOrder::factory()->create([
        'user_id' => $user->id,
        'code' => 'GSVCANCEL001',
        'total_amount' => 30000,
        'status' => 'pending',
    ]);

    app(WalletService::class)->debit(
        user: $user,
        amount: 30000,
        referenceType: GameServiceOrder::class,
        referenceId: $order->id,
        description: "Thanh toán đơn dịch vụ game {$order->code}",
        idempotencyKey: (string) Str::uuid(),
    );

    expect($wallet->refresh()->balance)->toBe('70000.00')
        ->and($wallet->total_spent)->toBe('30000.00');

    $this->actingAs($user)
        ->delete(route('account.game-service-orders.cancel', $order))
        ->assertRedirect(route('account.game-service-orders.index'))
        ->assertSessionHas('success', 'Đã hủy đơn GSVCANCEL001 và hoàn 30.000đ vào số dư NapCarot.');

    expect($order->refresh()->status)->toBe('cancelled')
        ->and($wallet->refresh()->balance)->toBe('100000.00')
        ->and($wallet->total_spent)->toBe('0.00')
        ->and(WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', GameServiceOrder::class)
            ->where('reference_id', $order->id)
            ->where('type', 'refund')
            ->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('account.game-service-orders.cancel', $order))
        ->assertRedirect()
        ->assertSessionHasErrors('order');

    expect($wallet->refresh()->balance)->toBe('100000.00')
        ->and(WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('reference_type', GameServiceOrder::class)
            ->where('reference_id', $order->id)
            ->where('type', 'refund')
            ->count())->toBe(1);
});

test('owner can cancel a legacy unpaid pending order without receiving unearned balance', function (): void {
    $user = User::factory()->create();
    $wallet = $user->wallet()->firstOrFail();
    $wallet->forceFill(['balance' => 25000])->save();
    $order = GameServiceOrder::factory()->create([
        'user_id' => $user->id,
        'code' => 'GSVLEGACY01',
        'total_amount' => 50000,
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->delete(route('account.game-service-orders.cancel', $order))
        ->assertRedirect(route('account.game-service-orders.index'))
        ->assertSessionHas('success', 'Đã hủy đơn GSVLEGACY01.');

    expect($order->refresh()->status)->toBe('cancelled')
        ->and($wallet->refresh()->balance)->toBe('25000.00')
        ->and(WalletTransaction::query()->where('type', 'refund')->count())->toBe(0);
});

test('user cannot cancel another customer order or a non pending order', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $pendingOrder = GameServiceOrder::factory()->create([
        'user_id' => $owner->id,
        'status' => 'pending',
    ]);
    $processingOrder = GameServiceOrder::factory()->create([
        'user_id' => $owner->id,
        'status' => 'processing',
    ]);

    $this->actingAs($otherUser)
        ->delete(route('account.game-service-orders.cancel', $pendingOrder))
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete(route('account.game-service-orders.cancel', $processingOrder))
        ->assertRedirect()
        ->assertSessionHasErrors('order');

    expect($pendingOrder->refresh()->status)->toBe('pending')
        ->and($processingOrder->refresh()->status)->toBe('processing');
});

test('history presents four customer statuses and only allows pending cancellation', function (): void {
    $user = User::factory()->create();
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'processing']);
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'review']);
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'failed']);
    GameServiceOrder::factory()->create(['user_id' => $user->id, 'status' => 'cancelled']);

    $response = $this->actingAs($user)
        ->get(route('account.game-service-orders.index'))
        ->assertSuccessful()
        ->assertSee('bx bx-message-circle-dots text-lg', false)
        ->assertSee('bx bx-x-circle text-lg', false);

    expect(substr_count($response->getContent(), 'data-game-service-order-cancel-form'))->toBe(1);
    expect(substr_count($response->getContent(), 'Chờ duyệt'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Đang thực hiện'))->toBe(2)
        ->and(substr_count($response->getContent(), 'Hoàn thành'))->toBe(1)
        ->and(substr_count($response->getContent(), 'Trả về/hoàn tiền'))->toBe(2)
        ->and(substr_count($response->getContent(), 'Chat xem vấn đề'))->toBe(2);
});

test('returned order keeps chat available so the customer can understand the issue', function (): void {
    $user = User::factory()->create();
    $order = GameServiceOrder::factory()->create([
        'user_id' => $user->id,
        'status' => 'failed',
    ]);

    $this->actingAs($user)
        ->get(route('account.game-service-orders.chat', $order))
        ->assertSuccessful()
        ->assertSee('Trả về/hoàn tiền')
        ->assertSee('data-game-service-order-chat-support', false)
        ->assertSee('data-order-chat-form', false);
});
