<?php

use App\Models\ConfigRecharge;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;

function tenantRechargeConfigPayload(array $overrides = []): array
{
    return array_merge([
        'provider' => 'apibankvn_api',
        'bank_name' => 'MBBank',
        'account_name' => 'AGENCY OWNER',
        'account_number' => '0123456789',
        'qr_template' => 'https://img.vietqr.io/image/mbbank-{account_number}.png?addInfo={nd}',
        'transfer_prefix' => 'NAP',
        'api_key' => 'agency-api-key',
        'api_secret' => 'agency-api-secret',
        'webhook_secret' => 'agency-webhook-secret',
        'api_bank_id' => 86,
        'is_active' => true,
    ], $overrides);
}

test('child admin manages only their own apibankvn recharge configurations', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $child = Tenant::factory()->create(['name' => 'Agency Bank']);
    TenantDomain::factory()->for($child)->create(['domain' => 'agency-bank.test']);
    $admin = User::factory()->create(['tenant_id' => $child->id, 'role' => 'admin']);

    ConfigRecharge::query()->withoutGlobalScopes()->create([
        ...tenantRechargeConfigPayload(['account_number' => 'MAIN-ACCOUNT']),
        'tenant_id' => $main->id,
    ]);
    ConfigRecharge::query()->withoutGlobalScopes()->create([
        ...tenantRechargeConfigPayload(['account_number' => 'CHILD-ACCOUNT']),
        'tenant_id' => $child->id,
    ]);

    $this->actingAs($admin)
        ->getJson('http://agency-bank.test/api/admin-api/recharge-config')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.configs')
        ->assertJsonPath('data.configs.0.account_number', 'CHILD-ACCOUNT')
        ->assertJsonPath('data.site.is_main', false)
        ->assertJsonPath('data.site.allowed_providers', ['apibankvn_api'])
        ->assertJsonPath('data.site.callback_url', 'https://agency-bank.test/api/recharge/callbacks/apibankvn');

    $this->actingAs($admin)
        ->postJson('http://agency-bank.test/api/admin-api/recharge-config', tenantRechargeConfigPayload(['api_bank_id' => 99]))
        ->assertCreated()
        ->assertJsonPath('data.config.provider', 'apibankvn_api');

    expect(ConfigRecharge::query()->withoutGlobalScopes()->where('tenant_id', $child->id)->count())->toBe(2)
        ->and(ConfigRecharge::query()->withoutGlobalScopes()->where('tenant_id', $main->id)->count())->toBe(1);
});

test('child admin cannot create a manual recharge configuration', function (): void {
    $child = Tenant::factory()->create();
    TenantDomain::factory()->for($child)->create(['domain' => 'agency-manual.test']);
    $admin = User::factory()->create(['tenant_id' => $child->id, 'role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('http://agency-manual.test/api/admin-api/recharge-config', tenantRechargeConfigPayload(['provider' => 'manual']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');
});

test('apibankvn callback resolves credentials from the requested child domain', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $child = Tenant::factory()->create();
    TenantDomain::factory()->for($child)->create(['domain' => 'agency-callback.test']);

    ConfigRecharge::query()->withoutGlobalScopes()->create([
        ...tenantRechargeConfigPayload(['webhook_secret' => 'main-secret']),
        'tenant_id' => $main->id,
    ]);
    ConfigRecharge::query()->withoutGlobalScopes()->create([
        ...tenantRechargeConfigPayload(['webhook_secret' => 'child-secret']),
        'tenant_id' => $child->id,
    ]);

    $callback = [
        'bank_id' => 86,
        'sign' => md5('main-secret86'),
        'transaction_type' => 'credit',
        'transaction_id' => 'TENANT-CALLBACK-001',
        'amount' => 100_000,
    ];

    $this->withHeader('X-Webhook-Secret', 'main-secret')
        ->postJson('http://agency-callback.test/api/recharge/callbacks/apibankvn', $callback)
        ->assertForbidden();

    $this->withHeader('X-Webhook-Secret', 'child-secret')
        ->postJson('http://agency-callback.test/api/recharge/callbacks/apibankvn', [
            ...$callback,
            'sign' => md5('child-secret86'),
        ])
        ->assertNotFound();
});
