<?php

use App\Features\Topup\Services\OrderClaimService;
use App\Models\Game;
use App\Models\Order;
use App\Models\User;

test('verified user claims matching guest orders idempotently', function (): void {
    $game = Game::factory()->create();
    $user = User::factory()->create(['email' => 'Player@Example.com']);
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'email' => 'player@example.com',
        'normalized_email' => 'player@example.com',
    ]);
    $service = app(OrderClaimService::class);

    $service->claimGuestOrdersForUser($user);
    $service->claimGuestOrdersForUser($user);

    expect($order->refresh()->user_id)->toBe($user->id);
});

test('unverified user cannot claim guest orders', function (): void {
    $game = Game::factory()->create();
    $user = User::factory()->unverified()->create(['email' => 'player@example.com']);
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'email' => $user->email,
        'normalized_email' => $user->email,
    ]);

    app(OrderClaimService::class)->claimGuestOrdersForUser($user);

    expect($order->refresh()->user_id)->toBeNull();
});

test('wrong email cannot view guest order', function (): void {
    $game = Game::factory()->create();
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'email' => 'owner@example.com',
        'normalized_email' => 'owner@example.com',
    ]);

    $this->from(route('orders.lookup'))
        ->post(route('orders.lookup.submit'), ['code' => $order->code, 'email' => 'other@example.com'])
        ->assertRedirect(route('orders.lookup'))
        ->assertSessionHasErrors('code');

    $this->get(route('orders.show', $order))->assertForbidden();

    $this->postJson(route('orders.lookup.submit'), ['code' => $order->code, 'email' => 'other@example.com'])
        ->assertNotFound()
        ->assertJsonPath('status', false);
});

test('guest history unlock returns modal detail url and grants session access', function (): void {
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $order = Order::factory()->create([
        'game_id' => $game->id,
        'topup_package_id' => null,
        'email' => 'owner@example.com',
        'normalized_email' => 'owner@example.com',
        'package_name' => 'Gói 10.000đ',
    ]);

    $this->get(route('orders.details', $order))->assertForbidden();

    $this->postJson(route('orders.lookup.submit'), [
        'code' => $order->code,
        'email' => 'OWNER@example.com',
    ])
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.code', $order->code)
        ->assertJsonPath('data.detail_url', route('orders.details', $order));

    $this->get(route('orders.details', $order))
        ->assertSuccessful()
        ->assertSee('data-order-detail-state', false)
        ->assertSee('Tiến trình đơn hàng')
        ->assertSee('Thông tin đơn hàng')
        ->assertSee('Ngọc Rồng Online')
        ->assertSee('Gói 10.000đ')
        ->assertDontSee('provider_reference')
        ->assertDontSee('provider_response');
});

test('signed in user is redirected from guest lookup to account order history', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('orders.lookup'))
        ->assertRedirect(route('account.orders.index'));
});

test('only the account owner can load order modal details', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $order = Order::factory()->for($owner)->create([
        'game_id' => Game::factory(),
        'topup_package_id' => null,
        'email' => $owner->email,
        'normalized_email' => mb_strtolower($owner->email),
    ]);

    $this->actingAs($otherUser)
        ->get(route('orders.details', $order))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('orders.details', $order))
        ->assertSuccessful()
        ->assertSee($order->code);
});
