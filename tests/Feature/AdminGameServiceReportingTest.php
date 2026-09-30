<?php

use App\Models\GameServiceOrder;
use App\Models\User;

test('game service revenue report is restricted to platform administrators', function (): void {
    $this->getJson('/api/admin-api/reports/game-services')->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->getJson('/api/admin-api/reports/game-services')
        ->assertForbidden();
});

test('game service report separates order status held funds settlements and completed revenue', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $base = [
        'game_name' => 'Ngọc Rồng Online',
        'service_name' => 'Săn đệ tử',
        'package_name' => 'Gói tiêu chuẩn',
        'created_at' => '2026-09-10 08:00:00',
    ];

    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'processing',
        'collaborator_total_cost' => 30000,
        'collaborator_held_at' => '2026-09-10 08:05:00',
    ]);
    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'review',
        'collaborator_total_cost' => 20000,
        'collaborator_held_at' => '2026-09-10 08:05:00',
    ]);
    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'completed',
        'completed_at' => '2026-09-11 10:00:00',
        'total_amount' => 100000,
        'collaborator_total_cost' => 40000,
        'collaborator_held_at' => '2026-09-10 08:05:00',
        'collaborator_available_at' => '2026-09-14 10:00:00',
        'gross_profit' => 60000,
        'estimated_tax' => 5000,
        'net_profit' => 55000,
    ]);
    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'completed',
        'completed_at' => '2026-09-12 10:00:00',
        'total_amount' => 80000,
        'collaborator_total_cost' => 30000,
        'collaborator_settlement_amount' => 30000,
        'collaborator_held_at' => '2026-09-10 08:05:00',
        'collaborator_available_at' => '2026-09-12 09:00:00',
        'collaborator_settled_at' => '2026-09-12 10:05:00',
        'gross_profit' => 50000,
        'estimated_tax' => 4000,
        'net_profit' => 46000,
    ]);
    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'failed',
        'collaborator_total_cost' => 10000,
        'collaborator_held_at' => '2026-09-10 08:05:00',
        'collaborator_refunded_at' => '2026-09-11 09:00:00',
    ]);
    GameServiceOrder::factory()->create([
        ...$base,
        'status' => 'completed',
        'created_at' => '2026-08-01 08:00:00',
        'completed_at' => '2026-08-02 08:00:00',
        'total_amount' => 999000,
        'collaborator_total_cost' => 500000,
        'gross_profit' => 499000,
        'net_profit' => 499000,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/game-services?from=2026-09-10&to=2026-09-12')
        ->assertSuccessful()
        ->assertJsonPath('data.period.days', 3)
        ->assertJsonPath('data.orders.total_orders', 5)
        ->assertJsonPath('data.orders.processing_orders', 1)
        ->assertJsonPath('data.orders.review_orders', 1)
        ->assertJsonPath('data.orders.reported_completion_orders', 3)
        ->assertJsonPath('data.orders.completed_orders', 2)
        ->assertJsonPath('data.orders.failed_orders', 1)
        ->assertJsonPath('data.funds.working_hold', 50000)
        ->assertJsonPath('data.funds.pending_settlement', 40000)
        ->assertJsonPath('data.funds.total_unsettled', 90000)
        ->assertJsonPath('data.funds.settled', 30000)
        ->assertJsonPath('data.funds.reversed', 10000)
        ->assertJsonPath('data.financials.completed_orders', 2)
        ->assertJsonPath('data.financials.revenue', 180000)
        ->assertJsonPath('data.financials.collaborator_cost', 70000)
        ->assertJsonPath('data.financials.after_collaborator', 110000)
        ->assertJsonPath('data.financials.estimated_tax', 9000)
        ->assertJsonPath('data.financials.net_profit', 101000)
        ->assertJsonPath('data.breakdowns.games.0.name', 'Ngọc Rồng Online')
        ->assertJsonPath('data.breakdowns.services.0.name', 'Săn đệ tử')
        ->assertJsonCount(2, 'data.recent_completed_orders');
});

test('game service report reuses bounded date validation', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->getJson('/api/admin-api/reports/game-services?from=2026-09-12')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});
