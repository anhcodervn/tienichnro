<?php

use App\Models\User;
use App\Models\Wallet;

test('user list totals main wallet balances without admin or affiliate balances', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstUser = User::factory()->create(['role' => 'user']);
    $secondUser = User::factory()->create(['role' => 'user']);

    $admin->wallet()->update(['balance' => 900_000]);
    $firstUser->wallet()->update(['balance' => 120_000]);
    $secondUser->wallet()->update(['balance' => 80_000]);
    $firstUser->wallets()->create([
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 500_000,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/users')
        ->assertSuccessful()
        ->assertJsonPath('data.stats.total_user_wallet_balance', 200_000);
});
