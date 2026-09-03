<?php

use App\Features\Client\Wallet\Services\WalletDepositService;
use App\Features\Recharge\Services\RechargeBonusService;
use App\Models\ConfigRecharge;
use App\Models\PaymentTransaction;
use App\Models\RechargeBonusTier;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;

test('platform admin can manage multiple recharge bonus tiers', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);

    $response = $this->actingAs($admin)
        ->postJson('http://napcarot.com/api/admin-api/recharge-bonus-tiers', [
            'minimum_amount' => 500_000,
            'bonus_percent' => 7.5,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.tier.minimum_amount', 500_000)
        ->assertJsonPath('data.tier.bonus_basis_points', 750)
        ->assertJsonPath('data.tier.bonus_percent', 7.5);

    $tierId = $response->json('data.tier.id');

    $this->actingAs($admin)
        ->putJson("http://napcarot.com/api/admin-api/recharge-bonus-tiers/{$tierId}", [
            'minimum_amount' => 600_000,
            'bonus_percent' => 8,
            'is_active' => false,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.tier.minimum_amount', 600_000)
        ->assertJsonPath('data.tier.bonus_percent', 8)
        ->assertJsonPath('data.tier.is_active', false);

    $secondTierId = $this->actingAs($admin)
        ->postJson('http://napcarot.com/api/admin-api/recharge-bonus-tiers', [
            'minimum_amount' => 100_000,
            'bonus_percent' => 2,
            'is_active' => true,
        ])
        ->assertCreated()
        ->json('data.tier.id');

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/recharge-bonus-tiers')
        ->assertSuccessful()
        ->assertJsonCount(2, 'data.tiers')
        ->assertJsonPath('data.tiers.0.minimum_amount', 100_000)
        ->assertJsonPath('data.tiers.1.minimum_amount', 600_000);

    $this->actingAs($admin)
        ->postJson('http://napcarot.com/api/admin-api/recharge-bonus-tiers', [
            'minimum_amount' => 100_000,
            'bonus_percent' => 3,
            'is_active' => true,
        ])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->deleteJson("http://napcarot.com/api/admin-api/recharge-bonus-tiers/{$tierId}")
        ->assertSuccessful();

    $this->actingAs($admin)
        ->deleteJson("http://napcarot.com/api/admin-api/recharge-bonus-tiers/{$secondTierId}")
        ->assertSuccessful();

    expect(RechargeBonusTier::query()->count())->toBe(0);
});

test('child admin cannot manage recharge bonus tiers', function (): void {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->for($tenant)->create(['domain' => 'bonus-agency.test']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('http://bonus-agency.test/api/admin-api/recharge-bonus-tiers')
        ->assertForbidden();
});

test('recharge bonus selects the highest active threshold reached', function (): void {
    RechargeBonusTier::factory()->create(['minimum_amount' => 100_000, 'bonus_basis_points' => 200]);
    RechargeBonusTier::factory()->create(['minimum_amount' => 500_000, 'bonus_basis_points' => 500]);
    RechargeBonusTier::factory()->create(['minimum_amount' => 1_000_000, 'bonus_basis_points' => 1_000, 'is_active' => false]);

    $service = app(RechargeBonusService::class);

    expect($service->calculate(99_999))
        ->bonus_amount->toBe(0)
        ->credited_amount->toBe(99_999)
        ->and($service->calculate(100_000))
        ->bonus_percent->toBe(2.0)
        ->bonus_amount->toBe(2_000)
        ->credited_amount->toBe(102_000)
        ->and($service->calculate(800_000))
        ->minimum_amount->toBe(500_000)
        ->bonus_amount->toBe(40_000)
        ->credited_amount->toBe(840_000);
});

test('deposit request snapshots bonus and successful callback credits it only once', function (): void {
    $tier = RechargeBonusTier::factory()->create([
        'minimum_amount' => 500_000,
        'bonus_basis_points' => 500,
    ]);
    $config = ConfigRecharge::query()->create([
        'provider' => 'manual',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'is_active' => true,
    ]);
    $user = User::factory()->create();
    $deposit = app(WalletDepositService::class)->createRequest($user, 600_000, $config->id);

    expect($deposit->raw_data)
        ->bonus_tier_id->toBe($tier->id)
        ->bonus_basis_points->toBe(500)
        ->bonus_amount->toBe(30_000)
        ->credited_amount->toBe(630_000);

    $tier->update(['bonus_basis_points' => 1_000]);
    $deposit->update(['raw_data' => [...$deposit->raw_data, 'provider' => 'apibankvn_api']]);
    $apiConfig = ConfigRecharge::query()->create([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'NAP CAROT',
        'account_number' => '0123456789',
        'qr_template' => 'https://qr.test/{account_number}',
        'transfer_prefix' => 'NAP',
        'api_base_url' => 'https://apibankvn.test',
        'api_key' => 'api-key',
        'api_secret' => 'api-secret',
        'webhook_secret' => 'webhook-secret',
        'api_bank_id' => 12,
        'is_active' => true,
    ]);
    $deposit->update(['raw_data' => [...$deposit->fresh()->raw_data, 'recharge_config_id' => $apiConfig->id]]);
    $callback = [
        'bank_id' => 12,
        'sign' => md5('webhook-secret12'),
        'transaction_id' => 'BANK-BONUS-001',
        'transaction_type' => 'credit',
        'client_order_code' => $deposit->transaction_code,
        'transfer_content' => $deposit->content,
        'amount' => 600_000,
    ];

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), $callback)
        ->assertSuccessful()
        ->assertJsonPath('data.deposit_request.bonus_amount', 30_000)
        ->assertJsonPath('data.deposit_request.credited_amount', 630_000);

    $this->withHeader('X-Webhook-Secret', 'webhook-secret')
        ->postJson(route('api.recharge.callbacks.apibankvn'), $callback)
        ->assertSuccessful();

    $wallet = Wallet::query()->where('user_id', $user->id)->sole();
    $walletTransaction = WalletTransaction::query()->sole();

    expect($deposit->refresh()->status)->toBe('success')
        ->and($wallet->balance)->toBe('630000.00')
        ->and($wallet->total_recharge)->toBe('600000.00')
        ->and($walletTransaction->amount)->toBe('630000.00')
        ->and($walletTransaction->metadata['bonus_amount'])->toBe(30_000)
        ->and(PaymentTransaction::query()->count())->toBe(1);
});
