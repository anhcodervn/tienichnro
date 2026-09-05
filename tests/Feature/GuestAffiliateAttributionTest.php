<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Features\Affiliate\Services\AffiliateReferralService;
use App\Features\Topup\Services\OrderStatusService;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

function guestAffiliateCatalog(): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'price' => 90000,
        'provider_price' => 75000,
        'min_quantity' => 1,
        'max_quantity' => 10,
    ]);

    return [$game, $server, $package];
}

function guestAffiliateCheckoutPayload(Game $game, GameServer $server, TopupPackage $package, array $overrides = []): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'affiliate-guest', 'game_character' => ''],
        'email' => 'affiliate-guest@example.test',
        'payment_method' => PaymentMethod::BankTransfer->value,
        ...$overrides,
    ];
}

function enableGuestAffiliate(Tenant $tenant, TopupPackage $package, int $fixedAmount = 2500): void
{
    AffiliateProgram::factory()->create(['tenant_id' => $tenant->id, 'is_enabled' => true]);
    AffiliatePackageRate::factory()->create([
        'tenant_id' => $tenant->id,
        'topup_package_id' => $package->id,
        'commission_type' => AffiliatePackageRate::TYPE_FIXED,
        'fixed_amount' => $fixedAmount,
        'percentage_basis_points' => null,
        'is_active' => true,
    ]);
}

test('the first valid referral is stored for a fixed thirty day window', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    AffiliateProgram::factory()->create(['tenant_id' => $main->id, 'is_enabled' => true]);
    $firstReferrer = User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'FIRSTREF01']);
    $secondReferrer = User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'SECONDREF1']);

    $firstResponse = $this->get('http://napcarot.com/?ref='.$firstReferrer->referral_code);
    $firstPayload = session(AffiliateReferralService::SESSION_KEY);

    $firstResponse->assertSuccessful()->assertCookie(AffiliateReferralService::COOKIE_NAME);
    expect($firstPayload['code'])->toBe($firstReferrer->referral_code)
        ->and($firstPayload['tenant_id'])->toBe($main->id)
        ->and($firstPayload['captured_at'])->toBeString()
        ->and($firstPayload['expires_at'])->toBeString()
        ->and(AffiliateProfile::query()->withoutGlobalScopes()->where('user_id', $firstReferrer->id)->exists())->toBeTrue();

    $this->travel(1)->day();
    $secondResponse = $this->get('http://napcarot.com/?ref='.$secondReferrer->referral_code);

    $secondResponse->assertSuccessful()->assertCookieMissing(AffiliateReferralService::COOKIE_NAME);
    expect(session(AffiliateReferralService::SESSION_KEY.'.code'))->toBe($firstReferrer->referral_code)
        ->and(session(AffiliateReferralService::SESSION_KEY.'.expires_at'))->toBe($firstPayload['expires_at']);
});

test('guest checkout snapshots attribution and creates a commission without a user account', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    [$game, $server, $package] = guestAffiliateCatalog();
    enableGuestAffiliate($main, $package, 3000);
    $referrer = User::factory()->create([
        'tenant_id' => $main->id,
        'email' => 'partner@example.test',
        'referral_code' => 'GUESTREF01',
    ]);

    $this->get('http://napcarot.com/?ref=GUESTREF01')->assertSuccessful();
    $this->post(route('checkout.store'), guestAffiliateCheckoutPayload($game, $server, $package))->assertRedirect();

    $order = Order::query()->sole();
    $commission = AffiliateCommission::query()->sole();

    expect($order->user_id)->toBeNull()
        ->and($order->affiliate_referrer_id)->toBe($referrer->id)
        ->and($order->affiliate_attribution_source)->toBe(Order::AFFILIATE_SOURCE_COOKIE)
        ->and($order->affiliate_referral_code)->toBe('GUESTREF01')
        ->and($order->affiliate_attributed_at)->not->toBeNull()
        ->and($commission->referrer_id)->toBe($referrer->id)
        ->and($commission->referred_user_id)->toBeNull()
        ->and($commission->amount)->toBe(3000)
        ->and($commission->earned_at)->toBeNull();

    $this->actingAs($referrer)
        ->getJson('http://napcarot.com/api/client/affiliate')
        ->assertOk()
        ->assertJsonPath('data.referral.url', 'http://napcarot.com?ref=GUESTREF01')
        ->assertJsonPath('data.referral.orders_count', 1)
        ->assertJsonPath('data.referral.guest_orders_count', 1)
        ->assertJsonPath('data.commissions.0.referred_user', null);

    $admin = User::factory()->create(['tenant_id' => $main->id, 'role' => 'admin']);
    $this->actingAs($admin)
        ->getJson('http://napcarot.com/api/admin-api/affiliate')
        ->assertOk()
        ->assertJsonPath('data.commissions.orders', 1)
        ->assertJsonPath('data.commissions.guest_orders', 1);

    $order->forceFill(['payment_status' => PaymentStatus::Paid, 'paid_at' => now()])->save();
    app(OrderStatusService::class)->transition($order->refresh(), OrderStatus::Processing);
    app(OrderStatusService::class)->transition($order->refresh(), OrderStatus::Completed);

    expect($commission->refresh()->earned_at)->not->toBeNull()
        ->and($commission->available_at?->equalTo($commission->earned_at?->copy()->addDays(7)))->toBeTrue();
});

test('registered referral takes priority over a cookie from another partner', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    [$game, $server, $package] = guestAffiliateCatalog();
    enableGuestAffiliate($main, $package);
    $accountReferrer = User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'ACCOUNTREF']);
    $cookieReferrer = User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'COOKIEREF1']);
    $buyer = User::factory()->create([
        'tenant_id' => $main->id,
        'email' => 'registered-buyer@example.test',
        'referred_by' => $accountReferrer->id,
    ]);

    $this->get('http://napcarot.com/?ref='.$cookieReferrer->referral_code)->assertSuccessful();
    $this->actingAs($buyer)->post(route('checkout.store'), guestAffiliateCheckoutPayload($game, $server, $package, [
        'email' => null,
    ]))->assertRedirect();

    $order = Order::query()->sole();

    expect($order->affiliate_referrer_id)->toBe($accountReferrer->id)
        ->and($order->affiliate_attribution_source)->toBe(Order::AFFILIATE_SOURCE_REGISTERED)
        ->and(AffiliateCommission::query()->sole()->referrer_id)->toBe($accountReferrer->id);
});

test('guest self referral by matching email does not create attribution', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    [$game, $server, $package] = guestAffiliateCatalog();
    enableGuestAffiliate($main, $package);
    User::factory()->create([
        'tenant_id' => $main->id,
        'email' => 'same-person@example.test',
        'referral_code' => 'SELFREF001',
    ]);

    $this->get('http://napcarot.com/?ref=SELFREF001')->assertSuccessful();
    $this->post(route('checkout.store'), guestAffiliateCheckoutPayload($game, $server, $package, [
        'email' => 'SAME-PERSON@example.test',
    ]))->assertRedirect();

    expect(Order::query()->sole()->affiliate_referrer_id)->toBeNull()
        ->and(AffiliateCommission::query()->count())->toBe(0);
});

test('expired referral is not attached to a later guest order', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    [$game, $server, $package] = guestAffiliateCatalog();
    enableGuestAffiliate($main, $package);
    User::factory()->create(['tenant_id' => $main->id, 'referral_code' => 'EXPIRED001']);

    $this->get('http://napcarot.com/?ref=EXPIRED001')->assertSuccessful();
    $this->travel(31)->days();
    $this->post(route('checkout.store'), guestAffiliateCheckoutPayload($game, $server, $package))->assertRedirect();

    expect(Order::query()->sole()->affiliate_referrer_id)->toBeNull()
        ->and(AffiliateCommission::query()->count())->toBe(0);
});
