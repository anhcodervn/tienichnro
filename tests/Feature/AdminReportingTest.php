<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Game;
use App\Models\Order;
use App\Models\TopupProvider;
use App\Models\User;

test('topup report is restricted to administrators', function (): void {
    $this->getJson('/api/admin-api/reports/topup')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/reports/topup')
        ->assertForbidden();
});

test('admin report counts only paid completed orders by completion date and compares growth', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create(['name' => 'Ngọc Rồng Online']);
    $provider = TopupProvider::factory()->create(['name' => 'ACCNROVN']);

    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-20 23:00:00',
        'completed_at' => '2026-08-21 08:00:00',
        'total_amount' => 10000,
        'sale_unit_price' => 5000,
        'provider_unit_cost' => 4000,
        'provider_total_cost' => 8000,
        'gross_profit' => 2000,
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => 1,
        'pit_rate' => 0.5,
        'estimated_vat' => 100,
        'estimated_pit' => 50,
        'estimated_tax' => 150,
        'payment_fee' => 0,
        'other_cost' => 0,
        'net_profit' => 1850,
        'profit_margin' => 18.5,
        'quantity' => 2,
        'package_name' => 'Gói 10.000đ',
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-22 09:00:00',
        'completed_at' => '2026-08-22 09:10:00',
        'total_amount' => 20000,
        'sale_unit_price' => 20000,
        'provider_unit_cost' => 15000,
        'provider_total_cost' => 15000,
        'gross_profit' => 5000,
        'tax_enabled' => true,
        'tax_calculation_type' => 'revenue',
        'vat_rate' => 1,
        'pit_rate' => 0.5,
        'estimated_vat' => 200,
        'estimated_pit' => 100,
        'estimated_tax' => 300,
        'payment_fee' => 0,
        'other_cost' => 0,
        'net_profit' => 4700,
        'profit_margin' => 23.5,
        'quantity' => 1,
        'package_name' => 'Gói 20.000đ',
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-22 10:00:00',
        'completed_at' => null,
        'order_status' => OrderStatus::Processing,
        'total_amount' => 90000,
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-22 11:00:00',
        'completed_at' => null,
        'order_status' => OrderStatus::Failed,
        'total_amount' => 80000,
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-22 12:00:00',
        'completed_at' => '2026-08-22 12:10:00',
        'payment_status' => PaymentStatus::Refunded,
        'total_amount' => 70000,
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-22 13:00:00',
        'completed_at' => '2026-08-23 08:00:00',
        'total_amount' => 60000,
    ]);
    createReportingOrder($game, $provider, [
        'created_at' => '2026-08-20 08:00:00',
        'completed_at' => '2026-08-20 08:10:00',
        'total_amount' => 10000,
        'quantity' => 1,
        'sale_unit_price' => 10000,
        'provider_unit_cost' => 9000,
        'provider_total_cost' => 9000,
        'gross_profit' => 1000,
    ]);

    $response = $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/topup?from=2026-08-21&to=2026-08-22')
        ->assertSuccessful()
        ->assertJsonPath('data.period.days', 2)
        ->assertJsonPath('data.period.previous_from', '2026-08-19')
        ->assertJsonPath('data.period.previous_to', '2026-08-20')
        ->assertJsonPath('data.summary.successful_orders', 2)
        ->assertJsonPath('data.summary.successful_units', 3)
        ->assertJsonPath('data.summary.revenue', 30000)
        ->assertJsonPath('data.summary.total_revenue', 30000)
        ->assertJsonPath('data.summary.average_order_value', 15000)
        ->assertJsonPath('data.summary.provider_cost', 23000)
        ->assertJsonPath('data.summary.total_cost', 23000)
        ->assertJsonPath('data.summary.gross_profit', 7000)
        ->assertJsonPath('data.summary.total_gross_profit', 7000)
        ->assertJsonPath('data.summary.estimated_vat', 300)
        ->assertJsonPath('data.summary.estimated_pit', 150)
        ->assertJsonPath('data.summary.estimated_tax', 450)
        ->assertJsonPath('data.summary.total_estimated_tax', 450)
        ->assertJsonPath('data.summary.net_profit', 6550)
        ->assertJsonPath('data.summary.total_net_profit', 6550)
        ->assertJsonPath('data.summary.net_margin_percent', 21.8333)
        ->assertJsonPath('data.summary.tax_snapshot_orders', 2)
        ->assertJsonPath('data.summary.legacy_tax_orders', 0)
        ->assertJsonPath('data.summary.gross_margin_percent', 23.3)
        ->assertJsonPath('data.summary.unpriced_orders', 0)
        ->assertJsonPath('data.summary.completion_rate', 60)
        ->assertJsonPath('data.growth.revenue.previous', 10000)
        ->assertJsonPath('data.growth.revenue.percentage_change', 200)
        ->assertJsonPath('data.growth.gross_profit.previous', 1000)
        ->assertJsonPath('data.growth.gross_profit.percentage_change', 600)
        ->assertJsonPath('data.growth.successful_orders.percentage_change', 100)
        ->assertJsonPath('data.growth.successful_units.percentage_change', 200)
        ->assertJsonPath('data.status_overview.created_orders', 5)
        ->assertJsonPath('data.status_overview.completed_orders', 3)
        ->assertJsonPath('data.status_overview.processing_orders', 1)
        ->assertJsonPath('data.status_overview.failed_orders', 1)
        ->assertJsonPath('data.trend.0.date', '2026-08-21')
        ->assertJsonPath('data.trend.0.revenue', 10000)
        ->assertJsonPath('data.trend.0.provider_cost', 8000)
        ->assertJsonPath('data.trend.0.gross_profit', 2000)
        ->assertJsonPath('data.trend.0.estimated_tax', 150)
        ->assertJsonPath('data.trend.0.net_profit', 1850)
        ->assertJsonPath('data.trend.1.revenue', 20000)
        ->assertJsonPath('data.breakdowns.games.0.name', 'Ngọc Rồng Online')
        ->assertJsonPath('data.breakdowns.games.0.revenue', 30000)
        ->assertJsonPath('data.breakdowns.games.0.provider_cost', 23000)
        ->assertJsonPath('data.breakdowns.games.0.gross_profit', 7000)
        ->assertJsonPath('data.breakdowns.games.0.estimated_tax', 450)
        ->assertJsonPath('data.breakdowns.games.0.net_profit', 6550)
        ->assertJsonPath('data.breakdowns.providers.0.name', 'ACCNROVN')
        ->assertJsonCount(2, 'data.recent_successful_orders');

    expect(json_encode($response->json('data'), JSON_THROW_ON_ERROR))
        ->not->toContain('90000')
        ->not->toContain('80000')
        ->not->toContain('70000')
        ->not->toContain('60000');
});

test('admin report validates complete and bounded date ranges', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/topup?from=2026-08-22')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/topup?from=2026-08-22&to=2026-08-21')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/topup?from=2025-01-01&to=2026-08-22')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

/** @param array<string, mixed> $overrides */
function createReportingOrder(Game $game, TopupProvider $provider, array $overrides = []): Order
{
    return Order::factory()->create([
        'game_id' => $game->id,
        'topup_provider_id' => $provider->id,
        'payment_status' => PaymentStatus::Paid,
        'order_status' => OrderStatus::Completed,
        'completed_at' => '2026-08-22 08:00:00',
        'quantity' => 1,
        'total_amount' => 10000,
        ...$overrides,
    ]);
}

test('admin report separates legacy successful orders without provider cost snapshot', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $game = Game::factory()->create();
    $provider = TopupProvider::factory()->create();

    createReportingOrder($game, $provider, [
        'completed_at' => '2026-08-28 10:00:00',
        'total_amount' => 25000,
        'sale_unit_price' => null,
        'provider_unit_cost' => null,
        'provider_total_cost' => null,
        'gross_profit' => null,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/topup?from=2026-08-28&to=2026-08-28')
        ->assertSuccessful()
        ->assertJsonPath('data.summary.revenue', 25000)
        ->assertJsonPath('data.summary.provider_cost', 0)
        ->assertJsonPath('data.summary.gross_profit', 0)
        ->assertJsonPath('data.summary.priced_orders', 0)
        ->assertJsonPath('data.summary.unpriced_orders', 1)
        ->assertJsonPath('data.summary.unpriced_revenue', 25000)
        ->assertJsonPath('data.summary.tax_snapshot_orders', 0)
        ->assertJsonPath('data.summary.legacy_tax_orders', 1)
        ->assertJsonPath('data.summary.legacy_tax_revenue', 25000)
        ->assertJsonPath('data.summary.estimated_tax', 0)
        ->assertJsonPath('data.summary.net_profit', 0);
});
