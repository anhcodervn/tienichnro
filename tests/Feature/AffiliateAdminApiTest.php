<?php

use App\Models\AdminAuditLog;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TopupPackage;
use App\Models\User;

test('affiliate admin api requires an administrator', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);

    $this->getJson('http://napcarot.com/api/admin-api/affiliate')->assertUnauthorized();
    $this->actingAs($user)->getJson('http://napcarot.com/api/admin-api/affiliate')->assertForbidden();
});

test('a child-site admin only sees affiliate partners from that site', function (): void {
    $firstSite = Tenant::factory()->create(['name' => 'Affiliate Site A']);
    $secondSite = Tenant::factory()->create(['name' => 'Affiliate Site B']);
    TenantDomain::factory()->for($firstSite)->create(['domain' => 'affiliate-a.test']);
    TenantDomain::factory()->for($secondSite)->create(['domain' => 'affiliate-b.test']);
    $admin = User::factory()->create(['tenant_id' => $firstSite->id, 'role' => 'admin']);
    $firstProfile = AffiliateProfile::factory()->create([
        'tenant_id' => $firstSite->id,
        'user_id' => User::factory()->create(['tenant_id' => $firstSite->id])->id,
    ]);
    AffiliateProfile::factory()->create([
        'tenant_id' => $secondSite->id,
        'user_id' => User::factory()->create(['tenant_id' => $secondSite->id])->id,
    ]);

    $this->actingAs($admin)
        ->getJson('http://affiliate-a.test/api/admin-api/affiliate/partners')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $firstProfile->id)
        ->assertJsonPath('data.data.0.tenant_id', $firstSite->id);
});

test('platform admin gets a cross-site affiliate overview', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $child = Tenant::factory()->create(['name' => 'Affiliate Child']);
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $package = TopupPackage::factory()->create();
    $mainReferrer = User::factory()->create(['tenant_id' => $main->id]);
    $childReferrer = User::factory()->create(['tenant_id' => $child->id]);
    AffiliateProfile::factory()->create(['tenant_id' => $main->id, 'user_id' => $mainReferrer->id]);
    AffiliateProfile::factory()->create(['tenant_id' => $child->id, 'user_id' => $childReferrer->id]);
    AffiliateCommission::factory()->create([
        'tenant_id' => $main->id,
        'referrer_id' => $mainReferrer->id,
        'referred_user_id' => User::factory()->create(['tenant_id' => $main->id])->id,
        'topup_package_id' => $package->id,
        'amount' => 2000,
        'base_amount' => 100000,
        'earned_at' => now(),
        'available_at' => now()->addDays(7),
    ]);
    AffiliateCommission::factory()->create([
        'tenant_id' => $child->id,
        'referrer_id' => $childReferrer->id,
        'referred_user_id' => User::factory()->create(['tenant_id' => $child->id])->id,
        'topup_package_id' => $package->id,
        'amount' => 3000,
        'base_amount' => 150000,
        'earned_at' => now(),
        'available_at' => now()->addDays(7),
    ]);
    AffiliateCommission::factory()->create([
        'tenant_id' => $main->id,
        'referrer_id' => $mainReferrer->id,
        'referred_user_id' => User::factory()->create(['tenant_id' => $main->id])->id,
        'topup_package_id' => $package->id,
        'amount' => 7000,
        'base_amount' => 200000,
        'earned_at' => null,
        'available_at' => null,
    ]);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/affiliate')
        ->assertOk()
        ->assertJsonPath('data.partners.total', 2)
        ->assertJsonPath('data.commissions.pending', 5000)
        ->assertJsonPath('data.commissions.revenue', 250000)
        ->assertJsonCount(2, 'data.by_site');
});

test('admin configures percentage commission per package and changes are audited', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $package = TopupPackage::factory()->create([
        'denomination' => 100000,
        'price' => 100000,
        'provider_price' => 90000,
    ]);

    $this->actingAs($admin)->putJson('http://napcarot.com/api/admin-api/affiliate/configuration', [
        'site_id' => $main->id,
        'is_enabled' => true,
        'minimum_withdrawal' => 75000,
    ])->assertOk()
        ->assertJsonPath('data.is_enabled', true)
        ->assertJsonPath('data.minimum_withdrawal', 75000);

    $this->actingAs($admin)->putJson("http://napcarot.com/api/admin-api/affiliate/rates/{$package->id}", [
        'site_id' => $main->id,
        'commission_type' => AffiliatePackageRate::TYPE_PERCENTAGE,
        'fixed_amount' => null,
        'percentage' => 5.25,
        'is_active' => true,
    ])->assertOk()->assertJsonPath('data.percentage_basis_points', 525);

    expect(AffiliateProgram::query()->withoutGlobalScopes()->where('tenant_id', $main->id)->value('minimum_withdrawal'))->toBe(75000)
        ->and(AffiliatePackageRate::query()->withoutGlobalScopes()->where('tenant_id', $main->id)->where('topup_package_id', $package->id)->value('percentage_basis_points'))->toBe(525)
        ->and(AdminAuditLog::query()->withoutGlobalScopes()->where('tenant_id', $main->id)->whereIn('action', [
            'affiliate_program_updated',
            'affiliate_package_rate_updated',
        ])->count())->toBe(2);
});

test('admin can hold and release a pending commission for manual review', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $commission = AffiliateCommission::factory()->create([
        'tenant_id' => $main->id,
        'referrer_id' => User::factory()->create(['tenant_id' => $main->id])->id,
        'referred_user_id' => User::factory()->create(['tenant_id' => $main->id])->id,
        'earned_at' => now(),
        'available_at' => now()->addDays(7),
    ]);

    $this->actingAs($admin)->patchJson("http://napcarot.com/api/admin-api/affiliate/commissions/{$commission->id}", [
        'action' => 'flag',
        'hold_reason' => 'Đơn hàng cần đối soát thủ công.',
    ])->assertOk()
        ->assertJsonPath('data.is_flagged', true)
        ->assertJsonPath('data.hold_reason', 'Đơn hàng cần đối soát thủ công.');

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/affiliate/commissions?status=flagged')
        ->assertOk()
        ->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.data.0.id', $commission->id);

    $this->actingAs($admin)->patchJson("http://napcarot.com/api/admin-api/affiliate/commissions/{$commission->id}", [
        'action' => 'unflag',
    ])->assertOk()
        ->assertJsonPath('data.is_flagged', false)
        ->assertJsonPath('data.hold_reason', null);

    expect(AdminAuditLog::query()->withoutGlobalScopes()
        ->where('subject_type', AffiliateCommission::class)
        ->where('subject_id', $commission->id)
        ->count())->toBe(2);
});

test('withdrawal lists mask account numbers while authorized detail can reveal it', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $withdrawal = AffiliateWithdrawal::factory()->create([
        'tenant_id' => $main->id,
        'user_id' => $user->id,
        'bank_account_number' => '0123456789',
    ]);

    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/affiliate/withdrawals')
        ->assertOk()
        ->assertJsonPath('data.data.0.bank_account_number_masked', '******6789')
        ->assertJsonMissingPath('data.data.0.bank_account_number');
    $this->actingAs($admin)
        ->getJson("http://napcarot.com/api/admin-api/affiliate/withdrawals/{$withdrawal->id}")
        ->assertOk()
        ->assertJsonPath('data.bank_account_number', '0123456789');
});

test('client affiliate dashboard follows the site enable switch', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    $program = AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => false]);

    $this->actingAs($user)->getJson('http://napcarot.com/api/client/affiliate')->assertNotFound();
    $program->update(['is_enabled' => true]);
    $this->actingAs($user)->getJson('http://napcarot.com/api/client/affiliate')
        ->assertOk()
        ->assertJsonPath('data.program.holding_days', 7)
        ->assertJsonPath('data.program.minimum_conversion', 1000)
        ->assertJsonPath('data.referral.code', $user->referral_code);

    expect(AffiliateProfile::query()->withoutGlobalScopes()->where('user_id', $user->id)->exists())->toBeTrue();
});
