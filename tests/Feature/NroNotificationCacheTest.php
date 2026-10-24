<?php

use App\Features\NroNotification\Services\NotificationReadCacheService;
use App\Features\NroNotification\Services\NotificationRetentionService;
use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\NroServer;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Cache\FileStore;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    config(['services.nro_notifications.cache_store' => 'array']);
    $this->travelTo(now()->startOfMinute()->addSeconds(20));
    $this->seed(NroNotificationSeeder::class);
    $this->server = NroServer::query()->where('is_active', true)->firstOrFail();
    $this->code = CodeNotify::query()->where('system_key', 'OTHER')->firstOrFail();
    $this->readCache = app(NotificationReadCacheService::class);
    $this->feed = app(NroNotificationFeedService::class);
    $this->notifications = app(NroNotificationService::class);
    $this->notify = Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'CACHE ORIGINAL', 'time_start' => now()]);
});

test('warm notification snapshots reuse rows without executing SQL and keep their server clock fresh', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $first = $this->feed->snapshot(['limit' => 10]);
    $coldQueries = count(DB::getQueryLog());
    $this->travel(1)->seconds();
    DB::flushQueryLog();
    $second = $this->feed->snapshot(['limit' => 10]);

    expect($coldQueries)->toBeGreaterThan(0)->and(DB::getQueryLog())->toBe([])
        ->and($second['signature'])->toBe($first['signature'])
        ->and($second['server_time'])->not->toBe($first['server_time']);
});

test('cache isolates filters pages sizes and guest versus member results', function (): void {
    foreach (range(1, 35) as $number) {
        Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'ROW '.$number, 'time_start' => now()->subMinutes($number)]);
    }
    Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'OLDER PRIVATE HISTORY', 'time_start' => now()->subHours(2)]);
    $member = $this->feed->snapshot(['limit' => 100]);
    $guest = $this->feed->snapshot(['limit' => 100, '_preview_since' => now()->subHour()->format('Y-m-d H:i:s.u')]);
    $pageTwo = $this->feed->snapshot(['limit' => 2, 'page' => 2]);
    $filtered = $this->feed->snapshot(['limit' => 100, 'q' => 'OLDER PRIVATE HISTORY']);
    expect($member['count'])->toBe(37)->and($member['html'])->toContain('OLDER PRIVATE HISTORY')
        ->and($guest['total'])->toBe(30)->and($guest['count'])->toBe(10)->and($guest['html'])->not->toContain('OLDER PRIVATE HISTORY')
        ->and($pageTwo['count'])->toBe(2)->and($pageTwo['current_page'])->toBe(2)
        ->and($filtered['count'])->toBe(1);
});

test('guest cache is shared across precise cutoffs but still removes rows outside one hour', function (): void {
    $this->notify->update(['time_start' => now()->subHour()->addSecond()]);
    $first = $this->feed->snapshot(['_preview_since' => now()->subHour()->format('Y-m-d H:i:s.u')]);
    expect($first['count'])->toBe(1);
    $this->travel(2)->minutes();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $expired = $this->feed->snapshot(['_preview_since' => now()->subHour()->format('Y-m-d H:i:s.u')]);
    expect($expired['count'])->toBe(0)->and($expired['html'])->not->toContain('CACHE ORIGINAL')->and(DB::getQueryLog())->toBe([]);
    expect($this->feed->snapshot([])['count'])->toBe(1);
});

test('respawning pages follow deadlines from cached data without querying SQL again', function (): void {
    $this->notify->update(['boss_name' => 'Boss First', 'is_boss' => true, 'death_time' => now(), 'respawn_at' => now()->addSecond()]);
    Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'SECOND BOSS', 'boss_name' => 'Boss Second', 'is_boss' => true, 'time_start' => now(), 'death_time' => now(), 'respawn_at' => now()->addSeconds(30)]);
    $first = $this->feed->snapshot(['state' => 'respawning', 'limit' => 1, 'page' => 2]);
    expect($first['total'])->toBe(2)->and($first['count'])->toBe(1);
    $this->travel(2)->seconds();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $second = $this->feed->snapshot(['state' => 'respawning', 'limit' => 1, 'page' => 2]);
    expect($second['total'])->toBe(1)->and($second['count'])->toBe(0)->and(DB::getQueryLog())->toBe([]);
});

test('notification mutations invalidate only after commit and rolled back writes leave cached data valid', function (): void {
    $this->feed->snapshot([]);
    $generation = $this->readCache->store()->get('nro:read:data:generation');
    DB::transaction(function () use ($generation): void {
        $this->notify->update(['content' => 'COMMITTED UPDATE']);
        expect($this->readCache->store()->get('nro:read:data:generation'))->toBe($generation);
    });
    expect($this->feed->snapshot([])['html'])->toContain('COMMITTED UPDATE');
    $afterCommit = $this->readCache->store()->get('nro:read:data:generation');
    expect(fn () => DB::transaction(function (): void {
        $this->notify->update(['content' => 'ROLLED BACK']);
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);
    expect($this->readCache->store()->get('nro:read:data:generation'))->toBe($afterCommit)
        ->and($this->feed->snapshot([])['html'])->toContain('COMMITTED UPDATE')->not->toContain('ROLLED BACK');
    $this->notify->delete();
    expect($this->feed->snapshot([])['count'])->toBe(0);
});

test('catalog and availability reuse cached data and invalidate when admin settings or models change', function (): void {
    $boss = Boss::factory()->create(['is_active' => true]);
    $this->readCache->catalog();
    $this->readCache->validationOptions();
    $this->readCache->availability();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->readCache->catalog();
    $this->readCache->validationOptions();
    $this->readCache->availability();
    expect(DB::getQueryLog())->toBe([]);
    $boss->update(['name' => 'RENAMED BOSS']);
    expect($this->readCache->catalog()['bosses']->firstWhere('id', $boss->id)->name)->toBe('RENAMED BOSS');
    $boss->delete();
    expect($this->readCache->catalog()['bosses']->contains('id', $boss->id))->toBeFalse();
    $boss->restore();
    expect($this->readCache->catalog()['bosses']->contains('id', $boss->id))->toBeTrue();
    $this->server->update(['name' => 'RENAMED SERVER']);
    expect($this->readCache->catalog()['servers']->firstWhere('id', $this->server->id)->name)->toBe('RENAMED SERVER');
    $this->code->update(['name' => 'RENAMED TYPE']);
    expect($this->readCache->catalog()['notificationTypes']->firstWhere('id', $this->code->id)->name)->toBe('RENAMED TYPE');
    Setting::query()->updateOrCreate(['key' => 'tool_game_notifications_enabled'], ['type' => 'boolean', 'value' => 'false']);
    expect($this->readCache->availability()['is_enabled'])->toBeFalse();
});

test('bulk retention pruning invalidates previously cached notification rows', function (): void {
    $this->notify->update(['time_start' => now()->subDays(40)]);
    expect($this->feed->snapshot([])['count'])->toBe(1);
    app(NotificationRetentionService::class)->prune();
    expect($this->feed->snapshot([])['count'])->toBe(0);
});

test('new notifications refresh the cached list and remove superseded cache entries', function (): void {
    $this->feed->snapshot([]);
    $oldKeys = array_keys($this->readCache->store()->get('nro:read:data:keys'));
    Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'NEW IMMUTABLE NOTIFICATION', 'time_start' => now()]);
    $snapshot = $this->feed->snapshot([]);
    expect($snapshot['count'])->toBe(2)->and($snapshot['html'])->toContain('NEW IMMUTABLE NOTIFICATION');
    foreach ($oldKeys as $key) {
        expect($this->readCache->store()->has($key))->toBeFalse();
    }
    expect($this->readCache->store()->get('nro:read:data:keys'))->toHaveCount(1);
});

test('guest respawning cache refills its thirty row window as earlier bosses expire without SQL', function (): void {
    foreach (range(1, 32) as $number) {
        Notify::query()->create(['server_id' => $this->server->id, 'code_id' => $this->code->id, 'content' => 'Respawning '.$number,
            'boss_name' => 'Respawning '.$number, 'is_boss' => true, 'time_start' => now(), 'death_time' => now(), 'respawn_at' => now()->addSeconds($number)]);
    }
    $filters = ['state' => 'respawning', '_preview_since' => now()->subHour()->format('Y-m-d H:i:s.u'), 'page' => 3];
    expect($this->feed->snapshot($filters)['total'])->toBe(30);
    $this->travel(2)->seconds();
    DB::enableQueryLog();
    DB::flushQueryLog();
    $next = $this->feed->snapshot([...$filters, '_preview_since' => now()->subHour()->format('Y-m-d H:i:s.u')]);
    expect($next['total'])->toBe(30)->and($next['count'])->toBe(10)->and(DB::getQueryLog())->toBe([]);
});

test('notification data remains cached without a ttl until cron deletes it', function (): void {
    $this->notify->update(['time_start' => now()->subDays(40)]);
    $this->feed->snapshot([]);
    DB::table('notifies')->where('id', $this->notify->id)->update(['content' => 'EXTERNAL UPDATE']);
    $this->travel(2)->days();
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($this->feed->snapshot([])['html'])->toContain('CACHE ORIGINAL')->and(DB::getQueryLog())->toBe([]);
    $keys = array_keys($this->readCache->store()->get('nro:read:data:keys'));
    app(NotificationRetentionService::class)->prune();
    foreach ($keys as $key) {
        expect($this->readCache->store()->has($key))->toBeFalse();
    }
    expect($this->feed->snapshot([])['count'])->toBe(0);
});

test('an in flight cache fill cannot become current again after invalidation', function (): void {
    $stale = $this->readCache->remember('data', ['race' => true], function (): string {
        $this->readCache->invalidate('data');

        return 'STALE';
    }, 60);
    $fresh = $this->readCache->remember('data', ['race' => true], fn () => 'FRESH', 60);
    expect($stale)->toBe('STALE')->and($fresh)->toBe('FRESH');
});

test('database default cache automatically uses file storage for notification data', function (): void {
    config(['cache.default' => 'database', 'services.nro_notifications.cache_store' => null]);
    expect($this->readCache->store()->getStore())->toBeInstanceOf(FileStore::class);
});

test('warm public requests avoid notification table queries while validation remains enforced', function (): void {
    $this->actingAs(User::factory()->create());
    $url = '/api/nro/notifies?server_code='.$this->server->server_code.'&code='.$this->code->code.'&limit=1';
    $this->getJson($url)->assertOk()->assertJsonPath('meta.per_page', 1);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->getJson($url)->assertOk()->assertJsonPath('meta.per_page', 1);
    $notificationQueries = array_filter(DB::getQueryLog(), fn ($query) => preg_match('/\b(notifies|bosses|servers|code_notifies)\b/', $query['query']));
    expect($notificationQueries)->toBe([]);
    $this->getJson('/api/nro/notifies?server_code=999999')->assertUnprocessable();
});
