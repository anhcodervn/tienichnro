<?php

use App\Models\AffiliateConversion;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

test('affiliate balance converts to the main wallet once for an idempotency key', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    $user = User::factory()->create(['tenant_id' => $main->id]);
    AffiliateProfile::factory()->create(['tenant_id' => $main->id, 'user_id' => $user->id]);
    Wallet::query()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 10000,
    ]);
    $key = (string) Str::uuid();
    $payload = ['amount' => 4000, 'idempotency_key' => $key];

    $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/convert', $payload)->assertOk();
    $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/convert', $payload)->assertOk();

    expect((int) Wallet::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('type', Wallet::TYPE_AFFILIATE)->value('balance'))->toBe(6000)
        ->and((int) Wallet::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('type', Wallet::TYPE_MAIN)->value('balance'))->toBe(4000)
        ->and(AffiliateConversion::query()->withoutGlobalScopes()->where('idempotency_key', $key)->count())->toBe(1);
});

test('withdrawal keeps affiliate funds on hold until an admin marks it paid', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create([
        'tenant_id' => $main->id,
        'is_enabled' => true,
        'minimum_withdrawal' => 50000,
    ]);
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    AffiliateProfile::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'bank_account_number' => '0123456789',
    ]);
    Wallet::query()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 120000,
    ]);

    $response = $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/withdrawals', [
        'amount' => 80000,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('data.status', AffiliateWithdrawal::STATUS_REQUESTED);
    $withdrawalId = (int) $response->json('data.id');
    $wallet = Wallet::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('type', Wallet::TYPE_AFFILIATE)->firstOrFail();
    expect((int) $wallet->balance)->toBe(40000)->and((int) $wallet->hold_balance)->toBe(80000);

    $this->actingAs($admin)->patchJson("http://napcarot.com/api/admin-api/affiliate/withdrawals/{$withdrawalId}", [
        'action' => 'approve',
    ])->assertOk()->assertJsonPath('data.status', AffiliateWithdrawal::STATUS_APPROVED);
    $this->actingAs($admin)->patchJson("http://napcarot.com/api/admin-api/affiliate/withdrawals/{$withdrawalId}", [
        'action' => 'mark_paid',
        'bank_transaction_reference' => 'BANK-TX-001',
    ])->assertOk()->assertJsonPath('data.status', AffiliateWithdrawal::STATUS_PAID);

    $wallet->refresh();
    expect((int) $wallet->balance)->toBe(40000)
        ->and((int) $wallet->hold_balance)->toBe(0)
        ->and(AffiliateWithdrawal::query()->withoutGlobalScopes()->findOrFail($withdrawalId)->bank_transaction_reference)->toBe('BANK-TX-001');
});

test('rejecting a withdrawal returns held funds to the affiliate wallet', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true, 'minimum_withdrawal' => 10000]);
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    AffiliateProfile::factory()->create(['tenant_id' => $main->id, 'user_id' => $user->id]);
    Wallet::query()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 30000,
    ]);
    $withdrawalId = (int) $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/withdrawals', [
        'amount' => 20000,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->patchJson("http://napcarot.com/api/admin-api/affiliate/withdrawals/{$withdrawalId}", [
        'action' => 'reject',
        'admin_note' => 'Thông tin tài khoản chưa hợp lệ.',
    ])->assertOk()->assertJsonPath('data.status', AffiliateWithdrawal::STATUS_REJECTED);

    $wallet = Wallet::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('type', Wallet::TYPE_AFFILIATE)->firstOrFail();
    expect((int) $wallet->balance)->toBe(30000)->and((int) $wallet->hold_balance)->toBe(0);
});

test('conversion enforces the one thousand minimum and disabled-site guard', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $program = AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    $user = User::factory()->create(['tenant_id' => $main->id]);
    AffiliateProfile::factory()->create(['tenant_id' => $main->id, 'user_id' => $user->id]);
    Wallet::query()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'type' => Wallet::TYPE_AFFILIATE,
        'balance' => 10000,
    ]);

    $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/convert', [
        'amount' => 999,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable();

    $program->update(['is_enabled' => false]);
    $this->actingAs($user)->postJson('http://napcarot.com/api/client/affiliate/convert', [
        'amount' => 1000,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable();

    expect((int) Wallet::query()->withoutGlobalScopes()->where('user_id', $user->id)->where('type', Wallet::TYPE_AFFILIATE)->value('balance'))->toBe(10000);
});
