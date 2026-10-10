<?php

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('wallet ledger applies signed changes atomically and protects duplicate and mismatched requests', function (): void {
    $user = User::factory()->create();
    $service = app(WalletService::class);
    $credit = $service->apply($user, 100000, 'credit', 'test-credit', 'Nạp tiền');
    expect($credit->balance_before)->toBe(0)->and($credit->balance_after)->toBe(100000);
    expect($service->apply($user, 100000, 'credit', 'test-credit', 'Nạp tiền')->id)->toBe($credit->id);
    $debit = $service->apply($user, 30000, 'debit', 'test-debit', 'Mua gói');
    expect($debit->balance_before)->toBe(100000)->and($debit->balance_after)->toBe(70000);
    expect(fn () => $service->apply($user, 20000, 'credit', 'test-credit', 'Nạp tiền'))->toThrow(ValidationException::class);
    expect(fn () => $service->apply($user, 80000, 'debit', 'insufficient', 'Mua gói'))->toThrow(ValidationException::class);
    expect($service->wallet($user)->balance)->toBe(70000);
    $this->assertDatabaseCount('wallet_transactions', 2);
});

test('wallet changes roll back with their outer transaction', function (): void {
    $user = User::factory()->create();
    try {
        DB::transaction(function () use ($user): void {
            app(WalletService::class)->apply($user, 10000, 'credit', 'rollback', 'Rollback');
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    $this->assertDatabaseCount('wallets', 0);
    $this->assertDatabaseCount('wallet_transactions', 0);
});

test('wallet history is private to its owner and cannot be credited by a member', function (): void {
    $mine = User::factory()->create();
    $other = User::factory()->create();
    $service = app(WalletService::class);
    $service->apply($mine, 10000, 'credit', 'mine', 'My transaction');
    $service->apply($other, 10000, 'credit', 'other', 'Private transaction');
    $this->get('/tai-khoan/vi')->assertRedirect(route('login'));
    $this->actingAs($mine)->get('/tai-khoan/vi')->assertOk()->assertSee('My transaction')->assertDontSee('Private transaction');
    $this->postJson('/api/admin-api/wallets/'.$mine->id.'/adjust', [])->assertForbidden();
});

test('admin adjustments are audited and retry safely', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);
    $payload = ['direction' => 'credit', 'amount' => 50000, 'description' => 'Nạp thủ công', 'request_id' => (string) Str::uuid()];
    $this->postJson('/api/admin-api/wallets/'.$user->id.'/adjust', $payload)->assertUnauthorized();
    $this->actingAs($admin)->postJson('/api/admin-api/wallets/'.$user->id.'/adjust', $payload)->assertOk()->assertJsonPath('balance', 50000);
    $this->postJson('/api/admin-api/wallets/'.$user->id.'/adjust', $payload)->assertOk()->assertJsonPath('balance', 50000);
    $this->assertDatabaseCount('wallet_transactions', 1);
    expect(WalletTransaction::query()->first()->actor_id)->toBe($admin->id);
    $this->getJson('/api/admin-api/wallets')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/admin-api/wallets/'.$user->id.'/transactions')->assertOk()->assertJsonPath('data.0.amount', 50000);
    $this->postJson('/api/admin-api/wallets/'.$user->id.'/adjust', [...$payload, 'amount' => -1])->assertUnprocessable();
});
