<?php

use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProgram;
use App\Models\Game;
use App\Models\GlobalTopupPackage;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TopupPackage;
use App\Models\User;

test('guest sees the public affiliate policy and rates when the program is enabled', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $package = TopupPackage::factory()->create(['name' => 'Gói Công Khai 100K']);

    AffiliateProgram::factory()->create([
        'tenant_id' => $main->id,
        'is_enabled' => true,
        'minimum_withdrawal' => 75000,
    ]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $package->id,
        'commission_type' => AffiliatePackageRate::TYPE_FIXED,
        'fixed_amount' => 2500,
        'percentage_basis_points' => null,
        'is_active' => true,
    ]);

    $this->get('http://napcarot.com/cong-tac-vien')
        ->assertSuccessful()
        ->assertViewIs('client.affiliate.introduction')
        ->assertSeeText('Chương trình cộng tác viên')
        ->assertSeeText('7 ngày')
        ->assertSeeText('Từ 1.000đ')
        ->assertSeeText('Từ 75.000đ')
        ->assertSeeText('Gói Công Khai 100K')
        ->assertSeeText('2.500đ / sản phẩm')
        ->assertSeeText('Đăng ký để bắt đầu');
});

test('public affiliate policy only exposes rates from the current site', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $child = Tenant::factory()->create(['name' => 'Affiliate Public Child']);
    TenantDomain::factory()->for($child)->create(['domain' => 'affiliate-public.test']);
    $mainPackage = TopupPackage::factory()->create(['name' => 'Gói Riêng Site Chính']);
    $childPackage = TopupPackage::factory()->create(['name' => 'Gói Riêng Site Con']);

    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliateProgram::factory()->create([
        'tenant_id' => $child->id,
        'is_enabled' => true,
        'minimum_withdrawal' => 120000,
    ]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $main->id,
        'topup_package_id' => $mainPackage->id,
        'fixed_amount' => 9000,
    ]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $child->id,
        'topup_package_id' => $childPackage->id,
        'commission_type' => AffiliatePackageRate::TYPE_PERCENTAGE,
        'fixed_amount' => null,
        'percentage_basis_points' => 525,
    ]);

    $this->get('http://affiliate-public.test/cong-tac-vien')
        ->assertSuccessful()
        ->assertViewIs('client.affiliate.introduction')
        ->assertSeeText('Gói Riêng Site Con')
        ->assertSeeText('5.25%')
        ->assertSeeText('Từ 120.000đ')
        ->assertDontSeeText('Gói Riêng Site Chính')
        ->assertDontSeeText('9.000đ / sản phẩm');
});

test('public affiliate policy shows inherited global rates for every mapped game', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $globalPackage = GlobalTopupPackage::factory()->create();
    $games = Game::factory()->count(2)->sequence(
        ['name' => 'Game Global Alpha'],
        ['name' => 'Game Global Beta'],
    )->create(['package_mode' => 'global']);

    foreach ($games as $game) {
        TopupPackage::factory()->create([
            'game_id' => $game->id,
            'global_topup_package_id' => $globalPackage->id,
            'name' => 'Gói chung '.$game->name,
        ]);
    }

    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    AffiliateGlobalPackageRate::factory()->create([
        'tenant_id' => $main->id,
        'global_topup_package_id' => $globalPackage->id,
        'commission_type' => AffiliatePackageRate::TYPE_PERCENTAGE,
        'fixed_amount' => null,
        'percentage_basis_points' => 375,
    ]);

    $this->get('http://napcarot.com/cong-tac-vien')
        ->assertSuccessful()
        ->assertSeeText('Game Global Alpha')
        ->assertSeeText('Game Global Beta')
        ->assertSeeText('3.75%');
});

test('authenticated user still receives the affiliate vue dashboard shell', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);

    $this->actingAs($user)
        ->get('http://napcarot.com/cong-tac-vien')
        ->assertSuccessful()
        ->assertViewIs('app')
        ->assertSee('id="app"', false)
        ->assertDontSeeText('Ba bước để nhận hoa hồng');
});

test('affiliate page stays unavailable when the current site program is disabled', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $main->id]);
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => false]);

    $this->get('http://napcarot.com/cong-tac-vien')->assertNotFound();
    $this->actingAs($user)->get('http://napcarot.com/cong-tac-vien')->assertNotFound();
});

test('guest access to the affiliate dashboard api remains protected', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);

    $this->getJson('http://napcarot.com/api/client/affiliate')->assertUnauthorized();
});
