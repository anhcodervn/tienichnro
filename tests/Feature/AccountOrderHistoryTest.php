<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Game;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

/** @param array<string, mixed> $overrides */
function createAccountHistoryOrder(User $user, Game $game, array $overrides = []): Order
{
    return Order::factory()
        ->for($user)
        ->for($game)
        ->create([
            'topup_package_id' => null,
            'game_server_id' => null,
            'email' => $user->email,
            'normalized_email' => mb_strtolower($user->email),
            ...$overrides,
        ]);
}

test('guest is redirected to login from account order history', function (): void {
    $this->get(route('account.orders.index'))
        ->assertRedirect(route('login'));
});

test('account order history renders one responsive server paginated data table for owner only', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $ownerOrder = createAccountHistoryOrder($user, $game, [
        'game_account' => 'owner-player',
        'package_name' => 'Gói 100.000đ',
        'total_amount' => 85000,
    ]);
    $otherOrder = createAccountHistoryOrder($otherUser, $game, ['game_account' => 'private-other-player']);

    $this->actingAs($user)
        ->get(route('account.orders.index'))
        ->assertSuccessful()
        ->assertSee('Lịch sử đơn hàng')
        ->assertSee('data-order-filters', false)
        ->assertSee('data-order-datatable', false)
        ->assertSee('data-order-table', false)
        ->assertDontSee('data-order-mobile-list', false)
        ->assertSee('aria-label="Bảng lịch sử đơn hàng"', false)
        ->assertSee('Hiển thị <strong class="text-slate-900">1–1</strong>', false)
        ->assertSee('data-order-detail-modal', false)
        ->assertSee('data-order-detail-trigger', false)
        ->assertSee(route('orders.details', $ownerOrder), false)
        ->assertSee('name="payment_status"', false)
        ->assertSee('name="order_status"', false)
        ->assertSee('name="date_from"', false)
        ->assertSee($ownerOrder->code)
        ->assertSee('owner-player')
        ->assertSee('85.000đ')
        ->assertDontSee($otherOrder->code)
        ->assertDontSee('private-other-player');
});

test('account order history searches order fields and combines game status and date filters', function (): void {
    $user = User::factory()->create();
    $gameA = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $gameB = Game::factory()->create(['name' => 'Avatar Musik']);
    $matchingOrder = createAccountHistoryOrder($user, $gameA, [
        'game_account' => 'target-player',
        'package_name' => 'Gói Kim Cương',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'created_at' => Carbon::parse('2026-08-10 12:00:00'),
    ]);
    $wrongStatus = createAccountHistoryOrder($user, $gameA, [
        'game_account' => 'target-player-waiting',
        'payment_status' => PaymentStatus::Pending,
        'order_status' => OrderStatus::Pending,
        'created_at' => Carbon::parse('2026-08-10 13:00:00'),
    ]);
    $wrongGame = createAccountHistoryOrder($user, $gameB, [
        'game_account' => 'target-player-other-game',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'created_at' => Carbon::parse('2026-08-10 14:00:00'),
    ]);
    $outsideDate = createAccountHistoryOrder($user, $gameA, [
        'game_account' => 'target-player-old',
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'created_at' => Carbon::parse('2026-07-10 12:00:00'),
    ]);

    $this->actingAs($user)
        ->get(route('account.orders.index', [
            'q' => 'target-player',
            'game_id' => $gameA->id,
            'payment_status' => PaymentStatus::Paid->value,
            'order_status' => OrderStatus::Completed->value,
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
        ]))
        ->assertSuccessful()
        ->assertSee($matchingOrder->code)
        ->assertDontSee($wrongStatus->code)
        ->assertDontSee($wrongGame->code)
        ->assertDontSee($outsideDate->code);

    $this->actingAs($user)
        ->get(route('account.orders.index', ['q' => 'Gói Kim Cương']))
        ->assertSuccessful()
        ->assertSee($matchingOrder->code);
});

test('account order history validates filter values', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account.orders.index'))
        ->get(route('account.orders.index', [
            'payment_status' => 'unknown',
            'order_status' => 'unknown',
            'date_from' => '2026-09-10',
            'date_to' => '2026-08-10',
            'per_page' => 999,
        ]))
        ->assertRedirect(route('account.orders.index'))
        ->assertSessionHasErrors(['payment_status', 'order_status', 'date_from', 'date_to', 'per_page']);

    $this->actingAs($user)
        ->get(route('account.orders.index', ['date_from' => '2026-08-01']))
        ->assertSuccessful();

    $this->actingAs($user)
        ->get(route('account.orders.index', ['date_to' => '2026-08-31']))
        ->assertSuccessful();
});

test('account order history keeps active filters while paginating', function (): void {
    $user = User::factory()->create();
    $game = Game::factory()->create();

    foreach (range(1, 11) as $index) {
        createAccountHistoryOrder($user, $game, [
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Paid,
            'order_status' => OrderStatus::Completed,
            'total_amount' => 10000 + $index,
            'created_at' => now()->subMinutes($index),
        ]);
    }

    $firstPage = $this->actingAs($user)
        ->get(route('account.orders.index', [
            'payment_status' => PaymentStatus::Paid->value,
            'per_page' => 10,
            'sort' => 'amount_desc',
        ]))
        ->assertSuccessful()
        ->assertSee('page=2', false);

    expect($firstPage->content())
        ->toContain('payment_status=paid')
        ->toContain('per_page=10')
        ->toContain('sort=amount_desc');

    $this->actingAs($user)
        ->get(route('account.orders.index', [
            'payment_status' => PaymentStatus::Paid->value,
            'per_page' => 10,
            'sort' => 'amount_desc',
            'page' => 2,
        ]))
        ->assertSuccessful()
        ->assertSee('Tìm thấy <strong class="text-slate-900">11</strong> đơn hàng.', false);
});
