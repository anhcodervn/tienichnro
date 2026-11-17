<?php

use App\Models\CaptchaTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

test('global ledger is restricted to active administrators', function (): void {
    $url = '/api/admin-api/wallets/history?kind=money';
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'banned']))->getJson($url)->assertForbidden();
});

test('global ledger paginates safely and retains deleted user history', function (string $kind): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['username' => 'ledger-owner']);
    if ($kind === 'money') {
        WalletTransaction::factory()->count(21)->create(['wallet_id' => Wallet::factory()->create(['user_id' => $user->id])->id, 'actor_id' => $admin->id]);
    } else {
        CaptchaTransaction::factory()->count(21)->create(['user_id' => $user->id, 'actor_id' => $admin->id]);
    }
    $user->delete();
    $url = '/api/admin-api/wallets/history?kind='.$kind;
    $response = $this->actingAs($admin)->getJson($url)->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('total', 21)->assertJsonPath('last_page', 2)
        ->assertJsonPath('data.0.username', 'ledger-owner')->assertJsonPath('data.0.actor_username', $admin->username);
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing(['id', 'user_id', 'username', 'actor_username', 'direction', 'amount', 'balance_before', 'balance_after', 'description', 'created_at']);
    $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
})->with(['money', 'token']);

test('ledger filters combine search direction and inclusive dates', function (string $kind): void {
    $user = User::factory()->create(['username' => 'target']);
    $factory = $kind === 'money' ? WalletTransaction::factory()->state(['wallet_id' => Wallet::factory()->create(['user_id' => $user->id])->id]) : CaptchaTransaction::factory()->state(['user_id' => $user->id]);
    $match = $factory->create(['description' => 'refund', 'direction' => 'credit', 'created_at' => '2026-11-17 23:59:59']);
    $factory->create(['direction' => 'credit', 'created_at' => '2026-11-18 00:00:00']);
    $factory->create(['direction' => 'debit', 'created_at' => '2026-11-17 12:00:00']);
    $factory->create(['direction' => 'credit', 'created_at' => '2026-11-16 23:59:59']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson('/api/admin-api/wallets/history?'.http_build_query(['kind' => $kind, 'q' => 'target', 'direction' => 'credit', 'from' => '2026-11-17', 'to' => '2026-11-17']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    $this->getJson('/api/admin-api/wallets/history?kind='.$kind.'&q=refund')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin-api/wallets/history?kind='.$kind.'&from=&to=2026-11-17')->assertOk()->assertJsonCount(3, 'data');
})->with(['money', 'token']);

test('ledger rejects invalid filter inputs', function (array $filters): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->getJson('/api/admin-api/wallets/history?'.http_build_query($filters))->assertUnprocessable();
})->with([
    [['kind' => 'users']],
    [['kind' => 'money', 'direction' => 'invalid']],
    [['kind' => 'money', 'from' => '2026-11-18', 'to' => '2026-11-17']],
    [['kind' => 'token', 'page' => 0]],
    [['kind' => 'token', 'q' => str_repeat('a', 101)]],
    [['kind' => 'money', 'to' => 'invalid']],
]);

test('money and token histories remain separate', function (): void {
    WalletTransaction::factory()->create(['description' => 'money only']);
    CaptchaTransaction::factory()->create(['description' => 'token only']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson('/api/admin-api/wallets/history?kind=money')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'money only');
    $this->getJson('/api/admin-api/wallets/history?kind=token')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'token only');
});
