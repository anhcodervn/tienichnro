<?php

use App\Features\NroNotification\Services\NotificationRetentionService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\NroEventReceipt;
use Carbon\CarbonImmutable;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-03-31 12:30:00', config('app.timezone')));
    config()->set('services.internal_cron.key', 'private-cron-key');
});

function retentionNotify(string $code, CarbonImmutable $time, array $extra = []): Notify
{
    return Notify::query()->create(['server_id' => 1, 'code_id' => CodeNotify::withTrashed()->where('system_key', $code)->value('id'),
        'time_start' => $time, 'content' => 'Retention fixture', ...$extra]);
}

test('cron removes notifications exactly at each retention boundary and keeps newer records', function (string $code, string $cutoff): void {
    $boundary = CarbonImmutable::parse($cutoff, config('app.timezone'));
    $expired = retentionNotify($code, $boundary->subMicrosecond());
    $exact = retentionNotify($code, $boundary);
    $fresh = retentionNotify($code, $boundary->addMicrosecond());
    $this->withHeader('X-Cron-Key', 'private-cron-key')->postJson(route('nro.notifies.prune'))->assertOk()
        ->assertJsonPath('data.deleted.'.$code, 2)->assertJsonPath('data.total_deleted', 2);
    $this->assertDatabaseMissing('notifies', ['id' => $expired->id]);
    $this->assertDatabaseMissing('notifies', ['id' => $exact->id]);
    $this->assertDatabaseHas('notifies', ['id' => $fresh->id]);
    $this->postJson(route('nro.notifies.prune'))->assertOk()->assertJsonPath('data.total_deleted', 0);
})->with([
    ['BOSS', '2026-03-30 12:30:00'],
    ['SET_ACTIVATION', '2026-01-30 12:30:00'],
    ['MAINTENANCE', '2026-03-24 12:30:00'],
    ['CRYSTAL_UPGRADE', '2025-03-31 12:30:00'],
    ['ITEM_UPGRADE', '2025-03-31 12:30:00'],
    ['GOD_ITEM', '2026-02-28 12:30:00'],
    ['PERMANENT_ITEM', '2026-02-28 12:30:00'],
    ['OTHER', '2026-02-28 12:30:00'],
]);

test('cron uses stable system keys after types are renamed or soft deleted and preserves custom types', function (): void {
    $type = CodeNotify::query()->where('system_key', 'OTHER')->firstOrFail();
    $type->update(['code' => 'RENAMED_OTHER']);
    $type->delete();
    $old = retentionNotify('OTHER', CarbonImmutable::now()->subMonthsNoOverflow(2));
    $custom = CodeNotify::query()->create(['type_id' => $type->type_id, 'code' => 'CUSTOM', 'name' => 'Custom']);
    $keep = Notify::query()->create(['server_id' => 1, 'code_id' => $custom->id, 'content' => 'Custom content', 'time_start' => now()->subYears(3)]);
    $this->withToken('private-cron-key')->postJson(route('nro.notifies.prune'))->assertOk()
        ->assertJsonPath('data.deleted.OTHER', 1);
    $this->assertDatabaseMissing('notifies', ['id' => $old->id]);
    $this->assertDatabaseHas('notifies', ['id' => $keep->id]);
});

test('boss flags and legacy names use 24 hours even when assigned a general notification type', function (): void {
    foreach ([['is_boss' => true], ['boss_name' => 'Unknown Boss']] as $extra) {
        retentionNotify('OTHER', CarbonImmutable::now()->subHours(25), $extra);
    }
    $fresh = retentionNotify('OTHER', CarbonImmutable::now()->subHours(23), ['boss_name' => 'Living Boss']);
    $this->withHeader('X-Cron-Key', 'private-cron-key')->postJson(route('nro.notifies.prune'))->assertOk()
        ->assertJsonPath('data.deleted.BOSS', 2)->assertJsonPath('data.deleted.OTHER', 0);
    $this->assertDatabaseHas('notifies', ['id' => $fresh->id]);
});

test('notification deletion preserves event receipts so replay cannot recreate purged history', function (): void {
    $payload = ['server_code' => 1, 'content' => 'Expired event', 'code' => 'OTHER', 'event_id' => 'retention-event',
        'occurred_at' => now()->subMonthsNoOverflow(2)->toISOString()];
    app(NroNotificationService::class)->ingest($payload);
    $this->withHeader('X-Cron-Key', 'private-cron-key')->postJson(route('nro.notifies.prune'))->assertOk()->assertJsonPath('data.total_deleted', 1);
    expect(NroEventReceipt::query()->sole()->notify_id)->toBeNull();
    expect(app(NroNotificationService::class)->ingest($payload)['duplicate'])->toBeTrue();
    $this->assertDatabaseCount('notifies', 0);
});

test('cron rejects missing incorrect and query string credentials before deleting', function (?string $key): void {
    $old = retentionNotify('OTHER', CarbonImmutable::now()->subMonthsNoOverflow(2));
    if ($key !== null) {
        $this->withHeader('X-Cron-Key', $key);
    }
    $this->postJson(route('nro.notifies.prune', ['key' => 'private-cron-key']))->assertUnauthorized();
    $this->assertDatabaseHas('notifies', ['id' => $old->id]);
})->with([null, 'wrong-key']);

test('cron is disabled without a configured key and never permits GET deletion', function (): void {
    config()->set('services.internal_cron.key', '');
    $this->postJson(route('nro.notifies.prune'))->assertStatus(503);
    $this->getJson(route('nro.notifies.prune'))->assertStatus(405);
});

test('overlapping cron calls return conflict and the lock is released after success', function (): void {
    $lock = Cache::lock('nro:notification-retention', 300);
    expect($lock->get())->toBeTrue();
    $this->withHeader('X-Cron-Key', 'private-cron-key')->postJson(route('nro.notifies.prune'))->assertStatus(409)->assertHeader('Retry-After', '60');
    $lock->release();
    $this->postJson(route('nro.notifies.prune'))->assertOk();
    $next = Cache::lock('nro:notification-retention', 300);
    expect($next->get())->toBeTrue();
    $next->release();
});

test('failed pruning releases the lock for a later retry', function (): void {
    $this->mock(NotificationRetentionService::class)->shouldReceive('prune')->once()->andThrow(new RuntimeException('Database unavailable'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->withHeader('X-Cron-Key', 'private-cron-key')->postJson(route('nro.notifies.prune')))->toThrow(RuntimeException::class);
    $lock = Cache::lock('nro:notification-retention', 300);
    expect($lock->get())->toBeTrue();
    $lock->release();
});
