<?php

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

test('child admin sees only users from their website', function (): void {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->for($tenant)->create(['domain' => 'agency.test']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
    $member = User::factory()->create(['tenant_id' => $tenant->id]);
    $mainUser = User::factory()->create();

    $response = $this->actingAs($admin)->getJson('http://agency.test/api/admin-api/users');

    $response->assertSuccessful();
    $ids = collect($response->json('data.data'))->pluck('id');
    expect($ids)->toContain($admin->id, $member->id)->not->toContain($mainUser->id);
});

test('child admin cannot manage central catalog providers bonus tiers or tenants', function (string $endpoint): void {
    $tenant = Tenant::factory()->create();
    TenantDomain::factory()->for($tenant)->create(['domain' => 'agency.test']);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('http://agency.test'.$endpoint)
        ->assertForbidden();
})->with([
    '/api/admin-api/games',
    '/api/admin-api/topup-providers',
    '/api/admin-api/recharge-bonus-tiers',
    '/api/admin-api/tenants',
]);

test('platform admin can create a child site linked to a NapCarot billing account', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $billingUser = User::factory()->create([
        'tenant_id' => $main->id,
        'username' => 'daily-owner',
        'email' => 'owner@dailycarot.vn',
        'phone' => '0901234567',
        'full_name' => 'Daily Owner',
        'avatar' => 'https://example.com/avatar.png',
    ]);

    $this->actingAs($admin)->postJson('/api/admin-api/tenants', [
        'name' => 'Daily Carot',
        'slug' => 'daily-carot',
        'domain' => 'dailycarot.vn',
        'billing_user_id' => $billingUser->id,
        'allow_below_cost' => false,
    ])->assertCreated()->assertJsonPath('data.site.domain', 'dailycarot.vn');

    $tenant = Tenant::query()->where('slug', 'daily-carot')->sole();
    $childAdmin = User::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', 'admin')->sole();

    expect($tenant->billing_user_id)->toBe($billingUser->id)
        ->and($childAdmin->id)->not->toBe($billingUser->id)
        ->and($childAdmin->username)->toBe($billingUser->username)
        ->and($childAdmin->email)->toBe($billingUser->email)
        ->and($childAdmin->phone)->toBe($billingUser->phone)
        ->and($childAdmin->full_name)->toBe($billingUser->full_name)
        ->and($childAdmin->avatar)->toBe($billingUser->avatar)
        ->and($childAdmin->password)->toBe($billingUser->password)
        ->and($childAdmin->email_verified_at?->equalTo($billingUser->email_verified_at))->toBeTrue()
        ->and(Wallet::query()->withoutGlobalScopes()->where('user_id', $childAdmin->id)->value('tenant_id'))->toBe($tenant->id);
});

test('platform admin cannot create a child site from an admin inactive or child-site account', function (string $accountType): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $child = Tenant::factory()->create();
    $billingUser = match ($accountType) {
        'admin' => User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']),
        'inactive' => User::factory()->create(['tenant_id' => $main->id, 'status' => 'inactive']),
        'child' => User::factory()->create(['tenant_id' => $child->id]),
    };

    $this->actingAs($admin)->postJson('/api/admin-api/tenants', [
        'name' => 'Invalid Agency',
        'slug' => 'invalid-agency-'.$accountType,
        'domain' => "invalid-{$accountType}.test",
        'billing_user_id' => $billingUser->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('billing_user_id');
})->with(['admin', 'inactive', 'child']);

test('platform admin can paginate search and filter websites', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $billingUser = User::factory()->create(['tenant_id' => $main->id, 'username' => 'billing-search']);

    foreach (range(1, 12) as $number) {
        $tenant = Tenant::factory()->create([
            'name' => "Agency {$number}",
            'billing_user_id' => $billingUser->id,
            'status' => $number === 12 ? 'suspended' : 'active',
        ]);
        TenantDomain::factory()->for($tenant)->create(['domain' => "agency-{$number}.test"]);
    }

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/tenants?site_type=child&per_page=10')
        ->assertSuccessful()
        ->assertJsonCount(10, 'data.sites')
        ->assertJsonPath('data.meta.total', 12)
        ->assertJsonPath('data.meta.last_page', 2);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/tenants?search=agency-12.test&status=suspended')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data.sites')
        ->assertJsonPath('data.sites.0.name', 'Agency 12');

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/tenants?search=billing-search&site_type=child&status=active')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 11);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/tenants?status=invalid')
        ->assertUnprocessable();
});

test('platform admin can monitor billing balance and current order counts for each website', function (): void {
    $this->travelTo(Carbon::parse('2026-09-15 12:00:00'));

    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $billingUser->wallet()->update(['balance' => 1_250_000]);
    $child = Tenant::factory()->create([
        'name' => 'Agency Metrics',
        'billing_user_id' => $billingUser->id,
    ]);

    Order::factory()->count(2)->create(['tenant_id' => $child->id, 'created_at' => now()]);
    Order::factory()->create(['tenant_id' => $child->id, 'created_at' => now()->subDays(5)]);
    Order::factory()->create(['tenant_id' => $child->id, 'created_at' => now()->subMonth()]);

    $response = $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/tenants?search=Agency%20Metrics')
        ->assertSuccessful();

    $site = collect($response->json('data.sites'))->firstWhere('id', $child->id);

    expect($site)
        ->not->toBeNull()
        ->and($site['billing_balance'])->toBe(1_250_000)
        ->and($site['orders_today_count'])->toBe(2)
        ->and($site['orders_month_count'])->toBe(3)
        ->and($site['orders_count'])->toBe(4);
});

test('platform admin can edit a child site while main site identity remains protected', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $replacementBillingUser = User::factory()->create(['tenant_id' => $main->id]);
    $child = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    TenantDomain::factory()->for($child)->create(['domain' => 'old-agency.test']);

    $this->actingAs($admin)
        ->putJson("http://napcarot.com/api/admin-api/tenants/{$child->id}", [
            'name' => 'Updated Agency',
            'slug' => 'updated-agency',
            'domain' => 'updated-agency.test',
            'billing_user_id' => $replacementBillingUser->id,
            'status' => 'suspended',
            'allow_below_cost' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.site.domain', 'updated-agency.test')
        ->assertJsonPath('data.site.status', 'suspended');

    expect($child->refresh())
        ->name->toBe('Updated Agency')
        ->slug->toBe('updated-agency')
        ->billing_user_id->toBe($replacementBillingUser->id)
        ->allow_below_cost->toBeTrue();

    $this->actingAs($admin)
        ->putJson("http://napcarot.com/api/admin-api/tenants/{$main->id}", [
            'slug' => 'broken-main',
            'domain' => 'broken-main.test',
            'billing_user_id' => $replacementBillingUser->id,
        ])
        ->assertUnprocessable();
});

test('platform admin can monitor child orders while child admins remain isolated', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $platformAdmin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $child = Tenant::factory()->create(['name' => 'Daily Carot']);
    TenantDomain::factory()->for($child)->create(['domain' => 'daily-orders.test']);
    $childAdmin = User::factory()->create(['tenant_id' => $child->id, 'role' => 'admin']);
    $mainOrder = Order::factory()->create(['tenant_id' => $main->id]);
    $childOrder = Order::factory()->create(['tenant_id' => $child->id]);
    PaymentTransaction::query()->create([
        'tenant_id' => $child->id,
        'order_id' => $childOrder->id,
        'transaction_code' => $childOrder->code,
        'amount' => $childOrder->total_amount,
        'content' => 'CHILDPAY001',
        'transfer_reference' => 'CHILDPAY001',
        'status' => 'pending',
    ]);

    $this->actingAs($platformAdmin)
        ->getJson('http://napcarot.com/api/admin-api/orders')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => $mainOrder->code])
        ->assertJsonFragment(['code' => $childOrder->code])
        ->assertJsonFragment(['name' => 'Daily Carot']);

    $this->actingAs($platformAdmin)
        ->getJson("http://napcarot.com/api/admin-api/orders?tenant_id={$child->id}")
        ->assertSuccessful()
        ->assertJsonFragment(['code' => $childOrder->code])
        ->assertJsonFragment(['payment_transfer_content' => 'CHILDPAY001'])
        ->assertJsonMissing(['code' => $mainOrder->code]);

    $this->actingAs($platformAdmin)
        ->getJson('http://napcarot.com/api/admin-api/orders?search=CHILDPAY001')
        ->assertSuccessful()
        ->assertJsonPath('data.meta.total', 1)
        ->assertJsonPath('data.data.0.code', $childOrder->code)
        ->assertJsonPath('data.data.0.payment_transfer_content', 'CHILDPAY001');

    $this->actingAs($platformAdmin)
        ->getJson("http://napcarot.com/api/admin-api/orders/{$childOrder->code}")
        ->assertSuccessful()
        ->assertJsonPath('data.payment_transfer_content', 'CHILDPAY001');

    $this->actingAs($childAdmin)
        ->getJson('http://daily-orders.test/api/admin-api/orders')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => $childOrder->code])
        ->assertJsonMissing(['code' => $mainOrder->code]);

    $this->actingAs($childAdmin)
        ->getJson("http://daily-orders.test/api/admin-api/orders/{$mainOrder->code}")
        ->assertNotFound();

    $this->actingAs($platformAdmin)
        ->putJson("http://napcarot.com/api/admin-api/orders/{$childOrder->code}", [
            'action' => 'cancel',
            'reason' => 'Kiểm tra vận hành từ website mẹ.',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.order_status', 'cancelled');
});
