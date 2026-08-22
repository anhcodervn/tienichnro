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
});
