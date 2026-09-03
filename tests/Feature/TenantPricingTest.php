<?php

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Utils\Site;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('child selling price is based on the NapCarot package cost and not main storefront markup', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $tenant = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    $package = TopupPackage::factory()->create(['price' => 90000, 'original_price' => 100000, 'provider_price' => 80000]);
    TenantPackagePrice::factory()->for($tenant)->for($package, 'package')->create(['markup_amount' => 7000]);

    $price = Site::for($tenant, fn (): array => app(TopupPackagePricingService::class)->resolve($package));

    expect($price['tenant_cost_price'])->toBe(90000)
        ->and($price['final_price'])->toBe(97000)
        ->and($price['tenant_profit'])->toBe(7000);
});

test('child website cannot save a selling price below current cost by default', function (): void {
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $tenant = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    $package = TopupPackage::factory()->create(['price' => 90000]);
    TenantPackagePrice::factory()->for($tenant)->for($package, 'package')->create([
        'pricing_mode' => 'fixed', 'fixed_price' => 80000, 'markup_amount' => null,
    ]);

    Site::for($tenant, fn () => app(TopupPackagePricingService::class)->resolve($package));
})->throws(ValidationException::class);

test('paid child order debits customer retail and linked NapCarot account cost atomically', function (): void {
    Mail::fake();
    Queue::fake();
    $main = Tenant::query()->where('is_main', true)->firstOrFail();
    $billingUser = User::factory()->create(['tenant_id' => $main->id]);
    $billingUser->wallet()->update(['balance' => 90000]);
    $tenant = Tenant::factory()->create(['billing_user_id' => $billingUser->id]);
    TenantDomain::factory()->for($tenant)->create(['domain' => 'pricing.test']);
    $customer = User::factory()->create(['tenant_id' => $tenant->id]);
    $customer->wallet()->update(['balance' => 95000]);
    $package = TopupPackage::factory()->create(['price' => 90000, 'original_price' => 100000, 'provider_price' => 80000]);
    $server = GameServer::factory()->for($package->game)->create();
    TenantPackagePrice::factory()->for($tenant)->for($package, 'package')->create(['markup_amount' => 5000]);

    $this->actingAs($customer)->post('http://pricing.test/dat-hang', [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $package->game_id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'player-01'],
        'payment_method' => 'wallet',
    ])->assertRedirect();

    $order = Order::query()->sole();
    expect($order->tenant_id)->toBe($tenant->id)
        ->and($order->billing_user_id)->toBe($billingUser->id)
        ->and($order->tenant_cost_total)->toBe(90000)
        ->and((int) $order->total_amount)->toBe(95000)
        ->and($order->tenant_profit)->toBe(5000)
        ->and(Site::for($tenant, fn (): ?int => $order->fresh()?->billingUser?->id))->toBe($billingUser->id)
        ->and((int) $customer->wallet()->value('balance'))->toBe(0)
        ->and((int) $billingUser->wallet()->value('balance'))->toBe(0)
        ->and(WalletTransaction::query()->count())->toBe(2)
        ->and(WalletTransaction::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(WalletTransaction::query()->withoutGlobalScopes()->where('tenant_id', $main->id)->count())->toBe(1);
});
