<?php

use App\Enums\PaymentMethod;
use App\Enums\TaxCalculationType;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\Order;
use App\Models\TopupPackage;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Mail::fake();
    Queue::fake();
});

test('checkout snapshots estimated revenue tax and keeps the old order unchanged after settings change', function (): void {
    app(SettingStore::class)->putMany([
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => '1.0000',
        'pit_rate' => '0.5000',
    ]);
    [$game, $server, $package] = taxSnapshotCatalog();

    $this->post(route('checkout.store'), taxSnapshotPayload($game, $server, $package))->assertRedirect();

    $order = Order::query()->sole();

    expect((int) $order->total_amount)->toBe(805_000)
        ->and((int) $order->provider_total_cost)->toBe(803_000)
        ->and((int) $order->gross_profit)->toBe(2_000)
        ->and($order->tax_enabled)->toBeTrue()
        ->and($order->tax_calculation_type)->toBe(TaxCalculationType::Revenue)
        ->and((float) $order->vat_rate)->toBe(1.0)
        ->and((float) $order->pit_rate)->toBe(0.5)
        ->and((int) $order->estimated_vat)->toBe(8_050)
        ->and((int) $order->estimated_pit)->toBe(4_025)
        ->and((int) $order->estimated_tax)->toBe(12_075)
        ->and((int) $order->payment_fee)->toBe(0)
        ->and((int) $order->other_cost)->toBe(0)
        ->and((int) $order->net_profit)->toBe(-10_075);

    app(SettingStore::class)->putMany(['vat_rate' => '2.0000', 'pit_rate' => '1.0000']);

    expect((int) $order->refresh()->estimated_tax)->toBe(12_075)
        ->and((int) $order->net_profit)->toBe(-10_075);
});

test('tax settings reject the future profit calculation mode', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/tax', [
            'tax_enabled' => true,
            'tax_calculation_type' => 'profit',
            'vat_rate' => '1.0000',
            'pit_rate' => '0.5000',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('status', false)
        ->assertJsonCount(1, 'data.errors.tax_calculation_type');

    $this->actingAs($admin)
        ->patchJson('/api/admin-api/settings/tax', [
            'tax_enabled' => true,
            'tax_calculation_type' => 'revenue',
            'vat_rate' => '1.0000',
            'pit_rate' => '0.5000',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.settings.tax_enabled', true)
        ->assertJsonPath('data.settings.tax_calculation_type', 'revenue');
});

/** @return array{Game, GameServer, TopupPackage} */
function taxSnapshotCatalog(): array
{
    $game = Game::factory()->create();
    $server = GameServer::factory()->for($game)->create();
    $package = TopupPackage::factory()->for($game)->create([
        'provider_price' => 803_000,
        'price' => 805_000,
        'original_price' => 805_000,
    ]);

    return [$game, $server, $package];
}

/** @return array<string, mixed> */
function taxSnapshotPayload(Game $game, GameServer $server, TopupPackage $package): array
{
    return [
        'idempotency_key' => (string) Str::uuid(),
        'game_id' => $game->id,
        'server_id' => $server->id,
        'package_id' => $package->id,
        'purchase_mode' => 'single',
        'single_quantity' => 1,
        'recipient_fields' => ['game_account' => 'tax-snapshot-player'],
        'email' => 'tax-snapshot@example.com',
        'payment_method' => PaymentMethod::BankTransfer->value,
    ];
}
