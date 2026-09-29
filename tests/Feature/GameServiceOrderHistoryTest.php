<?php

use App\Models\GameServiceOrder;
use App\Models\User;

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
