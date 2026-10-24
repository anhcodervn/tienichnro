<?php

use App\Models\NroEventReceipt;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->travelTo(now()->startOfSecond());
});

test('pruning receipts after 24 hours preserves notifications but permits old events to be replayed', function (): void {
    $payload = [
        'server_id' => 1,
        'event_id' => 'receipt-retention-replay',
        'content' => 'Receipt retention audit message',
        'occurred_at' => now()->toIso8601String(),
    ];
    $id = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->json('data.id');

    $this->travel(25)->hours();
    $this->postJson('/api/admin-api/nro/notifies', $payload)->assertOk();
    $this->assertDatabaseCount('notifies', 1);

    expect(NroEventReceipt::query()->where('created_at', '<', now()->subDay())->delete())->toBe(1);
    $this->assertDatabaseHas('notifies', ['id' => $id]);

    $replayedId = $this->postJson('/api/admin-api/nro/notifies', $payload)->assertCreated()->json('data.id');
    expect($replayedId)->not->toBe($id);
    $this->assertDatabaseCount('notifies', 2);
});

test('delayed events are accepted and need retention measured from receipt creation', function (): void {
    $this->postJson('/api/admin-api/nro/notifies', [
        'server_id' => 1,
        'event_id' => 'receipt-retention-delayed',
        'content' => 'Delayed receipt retention audit message',
        'occurred_at' => now()->subDays(2)->toIso8601String(),
    ])->assertCreated();

    expect(NroEventReceipt::query()->where('occurred_at', '<', now()->subDay())->count())->toBe(1)
        ->and(NroEventReceipt::query()->where('created_at', '<', now()->subDay())->count())->toBe(0);
});
