<?php

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\AffiliateCommission;
use App\Models\AffiliateConversion;
use App\Models\AffiliateProfile;
use App\Models\AffiliateWithdrawal;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('only the owner can subscribe to an affiliate dashboard channel', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $owner = User::factory()->create(['tenant_id' => $main->id]);
    $otherUser = User::factory()->create(['tenant_id' => $main->id]);
    $payload = [
        'socket_id' => '1234.5678',
        'channel_name' => "private-users.{$owner->id}.affiliate",
    ];

    $this->actingAs($owner)
        ->postJson('http://napcarot.com/broadcasting/auth', $payload)
        ->assertSuccessful();

    $this->actingAs($otherUser)
        ->postJson('http://napcarot.com/broadcasting/auth', $payload)
        ->assertForbidden();
});

test('affiliate data changes notify the correct dashboard owner', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $referrer = User::factory()->create(['tenant_id' => $main->id]);
    $package = TopupPackage::factory()->create();
    Event::fake([AffiliateDashboardUpdated::class]);

    AffiliateProfile::factory()->create(['tenant_id' => $main->id, 'user_id' => $referrer->id]);
    AffiliateCommission::factory()->create([
        'tenant_id' => $main->id,
        'referrer_id' => $referrer->id,
        'topup_package_id' => $package->id,
    ]);
    AffiliateConversion::factory()->create(['tenant_id' => $main->id, 'user_id' => $referrer->id]);
    AffiliateWithdrawal::factory()->create(['tenant_id' => $main->id, 'user_id' => $referrer->id]);
    User::factory()->create(['tenant_id' => $main->id, 'referred_by' => $referrer->id]);

    Event::assertDispatchedTimes(AffiliateDashboardUpdated::class, 5);
    Event::assertDispatched(
        AffiliateDashboardUpdated::class,
        fn (AffiliateDashboardUpdated $event): bool => $event->userId === $referrer->id,
    );
});
