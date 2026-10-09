<?php

use App\Features\NroNotification\Services\NroNotificationFeedService;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\NroEventReceipt;
use App\Models\NroServer;
use App\Models\User;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(NroNotificationSeeder::class);
    $this->boss = Boss::factory()->create(['code' => 'BROLY_3', 'name' => 'Broly 3', 'respawn_seconds' => 1800]);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $this->travelTo(now()->setDate(2026, 10, 6)->setTime(9, 30));
});
function nroSpawn(array $extra = []): array
{
    return ['server_id' => 1, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5', 'occurred_at' => '2026-10-06T09:10:00+07:00', ...$extra];
}
function nroDeath(array $extra = []): array
{
    return ['server_id' => 1, 'content' => 'Broly 3 vừa bị tiêu diệt bởi TunMeNRO', 'occurred_at' => '2026-10-06T09:18:00+07:00', ...$extra];
}
function nroSend(array $payload): TestResponse
{
    return test()->postJson('/api/admin-api/nro/notifies', $payload);
}

test('spawn and death produce one lifecycle with fixed respawn and raw messages', function (): void {
    $id = nroSend(nroSpawn())->assertCreated()->assertJsonPath('data.code', 'BOSS')->assertJsonPath('data.zone', 5)->json('data.id');
    nroSend(nroDeath())->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.state', 'dead')->assertJsonPath('data.killed_by', 'TunMeNRO');
    $this->assertDatabaseCount('notifies', 1);
    $notify = Notify::findOrFail($id);
    expect($notify->content)->toBe(nroSpawn()['content'])->and($notify->death_content)->toBe(nroDeath()['content'])
        ->and($notify->map_name)->toBe('Rừng Bamboo')->and($notify->respawn_at->toISOString())->toBe('2026-10-06T02:48:00.000000Z');
});

test('unknown respawn interval remains null and is rendered clearly', function (): void {
    $this->boss->update(['respawn_seconds' => null]);
    nroSend(nroSpawn())->assertCreated();
    nroSend(nroDeath())->assertOk()->assertJsonPath('data.respawn_at', null);
    $this->get('/thong-bao-game')->assertOk()->assertSee('Không xác định');
});

test('the live page and SSE use vertical rows with spawn and death details', function (): void {
    $id = nroSend(nroSpawn())->assertCreated()->json('data.id');
    $living = $this->get('/thong-bao-game')->assertOk()
        ->assertSee('role="list"', false)->assertSee('grid gap-3 transition-opacity', false)
        ->assertDontSee('md:grid-cols-2 xl:grid-cols-3', false)
        ->assertSee('Boss: Broly 3')->assertSee('Map: Rừng Bamboo')
        ->assertSee('Thời gian xuất hiện')->assertSee('06/10/2026 - 09:10:00')
        ->assertDontSee('Thời gian chết:')->assertDontSee('Người tiêu diệt:');
    expect(substr_count($living->getContent(), 'role="listitem"'))->toBe(1);
    nroSend(nroDeath())->assertOk();
    $snapshot = app(NroNotificationFeedService::class)->snapshot(['limit' => 100]);
    expect($snapshot['html'])->toContain('data-notify-id="'.$id.'"', 'role="listitem"', 'Thời gian chết:', '06/10/2026 - 09:18:00', 'Người tiêu diệt: TunMeNRO', 'data-nro-relative="2026-10-06T02:18:00.000000Z"');
    expect(substr_count($snapshot['html'], 'role="listitem"'))->toBe(1);
    $this->travel(2)->minutes();
    expect(app(NroNotificationFeedService::class)->snapshot(['limit' => 100])['signature'])->toBe($snapshot['signature']);
});

test('death closes only the matching boss on the matching server', function (): void {
    $first = nroSend(nroSpawn())->assertCreated()->json('data.id');
    $third = nroSend(nroSpawn(['server_id' => 3]))->assertCreated()->json('data.id');
    Boss::factory()->create(['name' => 'Super Broly']);
    $other = nroSend(nroSpawn(['server_id' => 3, 'content' => 'Super Broly vừa xuất hiện tại Rừng Bamboo khu 5']))->assertCreated()->json('data.id');
    nroSend(nroDeath(['server_id' => 3]))->assertOk()->assertJsonPath('data.id', $third);
    expect(Notify::findOrFail($first)->death_time)->toBeNull()->and(Notify::findOrFail($other)->death_time)->toBeNull();
});

test('respawn creates a new lifecycle and keeps historical kills untouched', function (): void {
    $old = nroSend(nroSpawn())->assertCreated()->json('data.id');
    nroSend(nroDeath(['content' => 'Broly 3 vừa bị tiêu diệt bởi A']))->assertOk();
    $new = nroSend(nroSpawn(['occurred_at' => '2026-10-06T09:48:00+07:00']))->assertCreated()->json('data.id');
    nroSend(nroDeath(['occurred_at' => '2026-10-06T09:56:00+07:00', 'content' => 'Broly 3 vừa bị tiêu diệt bởi B']))->assertOk()->assertJsonPath('data.id', $new);
    expect($old)->not->toBe($new)->and(Notify::findOrFail($old)->killed_by)->toBe('A')->and(Notify::findOrFail($new)->killed_by)->toBe('B');
    $this->assertDatabaseCount('notifies', 2);
});

test('unmatched deaths are retained without inventing or closing future lifecycles', function (): void {
    nroSend(nroDeath())->assertOk()->assertJsonPath('status', 'ignored_no_living_boss')->assertJsonPath('data', null);
    $this->assertDatabaseCount('notifies', 0);
    expect(NroEventReceipt::firstOrFail()->content)->toBe(nroDeath()['content']);
    nroSend(nroSpawn(['occurred_at' => '2026-10-06T09:48:00+07:00']))->assertCreated();
    nroSend(nroDeath())->assertOk()->assertJsonPath('duplicate', true);
    nroSend(nroDeath(['occurred_at' => '2026-10-06T09:19:00+07:00']))->assertOk()->assertJsonPath('status', 'ignored_no_living_boss');
    expect(Notify::firstOrFail()->death_time)->toBeNull();
});

test('duplicate deaths never close an older living record or a later spawn', function (): void {
    $old = nroSend(nroSpawn(['occurred_at' => '2026-10-06T09:00:00+07:00']))->assertCreated()->json('data.id');
    $new = nroSend(nroSpawn())->assertCreated()->json('data.id');
    $death = nroDeath(['event_id' => 'death-1']);
    nroSend($death)->assertOk()->assertJsonPath('data.id', $new);
    nroSend($death)->assertOk()->assertJsonPath('duplicate', true);
    nroSend([...$death, 'event_id' => 'death-2'])->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $new);
    expect(Notify::findOrFail($old)->death_time)->toBeNull();
    $next = nroSend(nroSpawn(['occurred_at' => '2026-10-06T09:48:00+07:00']))->assertCreated()->json('data.id');
    nroSend($death)->assertOk()->assertJsonPath('duplicate', true);
    expect(Notify::findOrFail($next)->death_time)->toBeNull();
    $this->assertDatabaseCount('notifies', 3);
});

test('duplicate spawns are idempotent and event ids are unique per server', function (): void {
    $payload = nroSpawn(['event_id' => 'spawn-1']);
    nroSend($payload)->assertCreated();
    nroSend($payload)->assertOk()->assertJsonPath('duplicate', true);
    nroSend([...$payload, 'server_id' => 3])->assertCreated();
    nroSend([...$payload, 'content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 6'])->assertUnprocessable()->assertJsonValidationErrors('event_id');
    $this->assertDatabaseCount('notifies', 2);
});

test('boss codes take priority and unknown ambiguous or inactive bosses become global', function (): void {
    Boss::factory()->create(['name' => 'Broly 3']);
    nroSend(nroSpawn())->assertCreated()->assertJsonPath('data.boss_global', true);
    nroSend(nroSpawn(['boss_code' => 'BROLY_3']))->assertCreated()->assertJsonPath('data.boss_id', $this->boss->id);
    nroSend(nroDeath(['boss_code' => 'MISSING']))->assertOk()->assertJsonPath('status', 'updated')->assertJsonPath('data.boss_global', true);
    $this->boss->update(['is_active' => false]);
    nroSend(nroSpawn(['occurred_at' => '2026-10-06T10:00:00+07:00', 'boss_code' => 'BROLY_3']))->assertCreated()->assertJsonPath('data.boss_global', true);
});

test('legacy boss input codes map to one BOSS code', function (): void {
    nroSend(nroSpawn(['code' => 'BOSS_APPEAR']))->assertCreated()->assertJsonPath('data.code', 'BOSS');
    nroSend(nroDeath(['code' => 'BOSS_DIE']))->assertOk()->assertJsonPath('data.code', 'BOSS');
    expect(CodeNotify::whereIn('code', ['BOSS_APPEAR', 'BOSS_DIE', 'BOSS_CHANGE_ZONE'])->count())->toBe(0);
    $this->assertDatabaseCount('notifies', 1);
});

test('general notifications retain their fields without boss lifecycle fields', function (string $code): void {
    nroSend(['server_id' => 1, 'code' => $code, 'content' => 'Thông báo game nguyên bản', 'occurred_at' => '2026-10-06T09:20:00+07:00', 'char_name' => 'Player', 'metadata' => ['level' => 5], 'expires_at' => '2026-10-06T10:20:00+07:00'])
        ->assertCreated()->assertJsonPath('data.code', $code)->assertJsonPath('data.char_name', 'Player')->assertJsonPath('data.metadata.level', 5);
    $notify = Notify::firstOrFail();
    foreach (['boss_id', 'death_content', 'death_time', 'killed_by', 'respawn_at'] as $field) {
        expect($notify->$field)->toBeNull();
    }
})->with(['SET_ACTIVATION', 'MAINTENANCE', 'CRYSTAL_UPGRADE', 'ITEM_UPGRADE', 'GOD_ITEM', 'PERMANENT_ITEM', 'OTHER']);

test('API and Blade filter living respawning history and server with pagination', function (): void {
    nroSend(nroSpawn())->assertCreated();
    nroSend(nroDeath())->assertOk();
    nroSend(nroSpawn(['server_id' => 3]))->assertCreated();
    $this->getJson('/api/nro/notifies?state=living')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.server_id', 3);
    $this->getJson('/api/nro/notifies?state=respawning')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.server_id', 1);
    $this->getJson('/api/nro/notifies?state=history&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
    $this->getJson('/api/nro/notifies?server_id=1&state=living')->assertOk()->assertJsonCount(0, 'data');
    $this->get('/thong-bao-game?state=respawning')->assertOk()->assertSee('TunMeNRO')->assertSee('data-nro-respawn', false);
    $this->travelTo(now()->setTime(10, 0));
    $this->getJson('/api/nro/notifies?state=respawning')->assertOk()->assertJsonCount(0, 'data');
});

test('admin can configure bosses while ordinary users and guests cannot ingest', function (): void {
    $id = $this->postJson('/api/admin-api/nro/bosses', ['code' => 'NEW_BOSS', 'name' => 'Boss mới', 'respawn_seconds' => null, 'is_active' => true])->assertCreated()->json('data.id');
    $this->patchJson('/api/admin-api/nro/bosses/'.$id, ['code' => 'NEW_BOSS', 'name' => 'Boss mới', 'respawn_seconds' => 60, 'is_active' => false])->assertOk()->assertJsonPath('data.respawn_seconds', 60);
    $this->getJson('/api/admin-api/nro/bosses')->assertOk();
    $this->actingAs(User::factory()->create(['role' => 'user']));
    nroSend(nroSpawn())->assertForbidden();
    $this->getJson('/api/admin-api/nro/bosses')->assertForbidden();
    $this->getJson('/api/nro/options')->assertOk()->assertJsonCount(22, 'data.servers');
    auth()->forgetGuards();
    nroSend(nroSpawn())->assertUnauthorized();
});

test('validation prevents partial writes', function (): void {
    foreach ([['occurred_at' => null], ['server_id' => 999], ['code' => 'BOSS', 'content' => 'invalid message'], ['content' => 'Broly 3 vừa xuất hiện tại Rừng Bamboo khu 999999']] as $extra) {
        nroSend(nroSpawn($extra))->assertUnprocessable();
    }
    $this->getJson('/api/nro/notifies?per_page=500')->assertUnprocessable();
    $this->getJson('/api/nro/notifies?state=invalid')->assertUnprocessable();
    $this->postJson('/api/admin-api/nro/bosses', ['code' => 'BROLY_3', 'name' => 'Duplicate', 'is_active' => true, 'respawn_seconds' => -1])->assertUnprocessable();
    $this->assertDatabaseCount('notifies', 0);
    $this->assertDatabaseCount('nro_event_receipts', 0);
});

test('raw whitespace is preserved and Blade escapes notification content', function (): void {
    $raw = '  Broly 3 vừa xuất hiện tại Rừng Bamboo khu 5  ';
    nroSend(nroSpawn(['content' => $raw]))->assertCreated();
    expect(Notify::firstOrFail()->content)->toBe($raw);
    nroSend(['server_id' => 1, 'content' => '<script>alert("x")</script>', 'occurred_at' => '2026-10-06T09:20:00+07:00'])->assertCreated();
    $this->get('/thong-bao-game')->assertOk()->assertDontSee('<script>alert("x")</script>', false)->assertSee('&lt;script&gt;', false);
});

test('changing respawn configuration preserves existing death history', function (): void {
    nroSend(nroSpawn())->assertCreated();
    nroSend(nroDeath())->assertOk();
    $before = Notify::firstOrFail()->respawn_at;
    $this->boss->update(['respawn_seconds' => 60]);
    expect(Notify::firstOrFail()->respawn_at->equalTo($before))->toBeTrue();
});

test('default seeding is repeatable and keeps existing server configuration', function (): void {
    NroServer::findOrFail(1)->update(['name' => 'Custom server', 'is_active' => false]);
    $this->seed(NroNotificationSeeder::class);
    $this->assertDatabaseCount('servers', 22);
    expect(NroServer::findOrFail(1)->name)->toBe('Custom server')->and(NroServer::findOrFail(22)->name)->toBe('Super 3');
    $this->assertDatabaseCount('code_notifies', 8);
});

test('disabling a boss makes new spawns global but still closes existing living lifecycles', function (): void {
    nroSend(nroSpawn())->assertCreated();
    $this->boss->update(['is_active' => false]);
    nroSend(nroDeath())->assertOk()->assertJsonPath('status', 'updated');
    nroSend(nroSpawn(['occurred_at' => '2026-10-06T09:48:00+07:00']))->assertCreated()->assertJsonPath('data.boss_global', true);
    $this->assertDatabaseCount('notifies', 2);
});

test('the game utility is discoverable from navigation and sitemap', function (): void {
    $this->get('/')->assertOk()->assertSee(route('nro.notifies.page'));
    $this->get('/sitemap-pages.xml')->assertOk()->assertSee(route('nro.notifies.page'));
});

test('zero second respawn interval is fixed rather than unknown', function (): void {
    $this->boss->update(['respawn_seconds' => 0]);
    nroSend(nroSpawn())->assertCreated();
    nroSend(nroDeath())->assertOk();
    $notify = Notify::firstOrFail();
    expect($notify->respawn_at)->not->toBeNull()->and($notify->respawn_at->equalTo($notify->death_time))->toBeTrue();
});

test('collector logs in for a real bearer token and persists spawn and death through the API', function (): void {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'password' => 'Collector-password-123']);
    auth()->forgetGuards();
    $login = $this->postJson('/api/auth/login', ['login' => $admin->email, 'password' => 'Collector-password-123'])
        ->assertOk()->assertJsonPath('token_type', 'Bearer');
    $token = $login->json('access_token');
    expect($token)->toBeString()->not->toBeEmpty();
    $this->withToken($token);
    $id = nroSend(nroSpawn(['event_id' => 'collector-spawn']))->assertCreated()->json('data.id');
    auth()->forgetGuards();
    nroSend(nroDeath(['event_id' => 'collector-death']))->assertOk()->assertJsonPath('data.id', $id);
    $this->assertDatabaseCount('notifies', 1);
    expect(Notify::findOrFail($id)->killed_by)->toBe('TunMeNRO');
});

test('SQL field names time_start and code_id are supported with consistent replay detection', function (): void {
    $code = CodeNotify::query()->where('code', 'MAINTENANCE')->firstOrFail();
    $payload = ['server_id' => '1', 'code_id' => $code->id, 'content' => 'Thông báo bảo trì nguyên bản', 'time_start' => '2026-10-06T10:00:00+07:00', 'event_id' => 'sql-message'];
    $id = nroSend($payload)->assertCreated()->assertJsonPath('data.code_id', $code->id)->json('data.id');
    nroSend(['server_id' => 1, 'code' => 'MAINTENANCE', 'content' => $payload['content'], 'occurred_at' => $payload['time_start'], 'event_id' => 'sql-message'])
        ->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
    nroSend([...$payload, 'code' => 'OTHER'])->assertUnprocessable()->assertJsonValidationErrors('code_id');
    nroSend([...$payload, 'code_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('code_id');
    $this->assertDatabaseCount('notifies', 1);
});

test('invalid credentials and ordinary user tokens cannot write notifications', function (): void {
    $user = User::factory()->create(['role' => 'user', 'password' => 'User-password-123']);
    auth()->forgetGuards();
    $this->postJson('/api/auth/login', ['login' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
    $token = $this->postJson('/api/auth/login', ['login' => $user->email, 'password' => 'User-password-123'])->assertOk()->json('access_token');
    auth()->forgetGuards();
    $this->withToken($token);
    nroSend(nroSpawn())->assertForbidden();
    auth()->forgetGuards();
    $this->withToken('invalid-token');
    nroSend(nroSpawn())->assertUnauthorized();
    $this->assertDatabaseCount('notifies', 0);
});

test('server codes resolve independent primary keys and preserve replay detection', function (): void {
    $server = NroServer::factory()->create(['server_code' => 200]);
    $payload = nroSpawn(['server_code' => 200, 'event_id' => 'server-code-spawn']);
    unset($payload['server_id']);

    $id = nroSend($payload)->assertCreated()->assertJsonPath('data.server_id', $server->id)
        ->assertJsonPath('data.server_code', 200)->json('data.id');
    nroSend(nroSpawn(['server_id' => $server->id, 'event_id' => 'server-code-spawn']))
        ->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
    $death = nroDeath(['server_code' => 200]);
    unset($death['server_id']);
    nroSend($death)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.state', 'dead');

    nroSend(nroSpawn())->assertCreated();
    $this->getJson('/api/nro/notifies?server_code=200')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $id);
    $this->getJson('/api/nro/options')->assertOk()->assertJsonFragment(['server_code' => 200]);
});

test('missing unknown inactive or conflicting server identifiers are rejected', function (): void {
    $payload = nroSpawn();
    unset($payload['server_id']);
    nroSend($payload)->assertUnprocessable()->assertJsonValidationErrors(['server_id', 'server_code']);
    nroSend([...$payload, 'server_code' => 999999])->assertUnprocessable()->assertJsonValidationErrors('server_code');
    nroSend(nroSpawn(['server_code' => 3]))->assertUnprocessable()->assertJsonValidationErrors('server_code');
    nroSend(nroSpawn(['server_code' => 1]))->assertCreated();
    NroServer::query()->where('server_code', 3)->update(['is_active' => false]);
    nroSend([...$payload, 'server_code' => 3])->assertUnprocessable()->assertJsonValidationErrors('server_code');
    $this->getJson('/api/nro/notifies?server_code=999999')->assertUnprocessable();
    $this->assertDatabaseCount('notifies', 1);
});

test('server seeding preserves an existing managed catalog without assigning primary keys', function (): void {
    NroServer::query()->delete();
    $server = NroServer::factory()->create(['id' => 100, 'server_code' => 1, 'name' => 'Custom server', 'is_active' => false]);

    $this->seed(NroNotificationSeeder::class);
    $this->seed(NroNotificationSeeder::class);

    $this->assertDatabaseCount('servers', 1);
    expect($server->fresh()->name)->toBe('Custom server')->and($server->fresh()->is_active)->toBeFalse();
    expect($server->fresh()->id)->toBe(100);
});

test('server codes are unique and required in the database', function (): void {
    expect(fn () => NroServer::factory()->create(['server_code' => 1]))->toThrow(QueryException::class);
    expect(fn () => NroServer::factory()->create(['server_code' => null]))->toThrow(QueryException::class);
});

test('server code migrations backfill existing ids without changing notification references', function (): void {
    $id = nroSend(nroSpawn(['event_id' => 'before-server-migration']))->assertCreated()->json('data.id');
    $migrationPaths = [
        '2026_10_08_091639_add_server_code_to_servers_table.php',
        '2026_10_08_091659_backfill_server_codes.php',
        '2026_10_08_091659_require_unique_server_codes.php',
    ];
    $migrations = array_map(fn (string $path) => require database_path('migrations/'.$path), $migrationPaths);
    foreach (array_reverse($migrations) as $migration) {
        $migration->down();
    }
    foreach ($migrations as $migration) {
        $migration->up();
    }

    expect(DB::table('servers')->whereColumn('server_code', '!=', 'id')->count())->toBe(0)
        ->and(NroServer::query()->count())->toBe(22)
        ->and(Notify::findOrFail($id)->server_id)->toBe(1)
        ->and(NroEventReceipt::firstOrFail()->server_id)->toBe(1);
    nroSend(nroSpawn(['server_code' => 1, 'event_id' => 'before-server-migration']))
        ->assertOk()->assertJsonPath('duplicate', true)->assertJsonPath('data.id', $id);
});

test('the live page initially loads the latest 10 notifications and respects a custom limit', function (): void {
    for ($index = 0; $index < 105; $index++) {
        nroSend(['server_id' => 1, 'content' => 'Notification '.$index,
            'occurred_at' => now()->subSeconds(105 - $index)->toISOString()])->assertCreated();
    }
    $this->get('/thong-bao-game')->assertOk()
        ->assertViewHas('limit', 10)->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 10 && $snapshot['total'] === 105)
        ->assertSee('Notification 104')->assertDontSee('Notification 94<', false)->assertSee('data-nro-stream', false);
    $snapshot = app(NroNotificationFeedService::class)->snapshot(['limit' => 3]);
    expect($snapshot['count'])->toBe(3)->and(strpos($snapshot['html'], 'Notification 104'))
        ->toBeLessThan(strpos($snapshot['html'], 'Notification 103'));
    $this->get('/thong-bao-game?limit=3')->assertOk()->assertViewHas('limit', 3)
        ->assertViewHas('snapshot', fn (array $snapshot): bool => $snapshot['count'] === 3)->assertDontSee('Notification 101');
});

test('live snapshots respect filters update deaths and recover the current state on reconnect', function (): void {
    $feed = app(NroNotificationFeedService::class);
    $stream = $feed->events(['server_code' => 1, 'state' => 'living', 'limit' => 1], 60, 0);
    expect($stream->current()->event)->toBe('notifications')->and($stream->current()->data['count'])->toBe(0);
    nroSend(nroSpawn(['server_id' => 3]))->assertCreated();
    $stream->next();
    expect($stream->current()->event)->toBe('heartbeat');
    nroSend(nroSpawn())->assertCreated();
    $stream->next();
    expect($stream->current()->event)->toBe('notifications')->and($stream->current()->data['count'])->toBe(1);
    nroSend(nroSpawn())->assertOk()->assertJsonPath('duplicate', true);
    $stream->next();
    expect($stream->current()->event)->toBe('heartbeat');
    nroSend(nroDeath())->assertOk();
    $stream->next();
    expect($stream->current()->event)->toBe('notifications')->and($stream->current()->data['count'])->toBe(0);
    $reconnected = iterator_to_array($feed->events(['server_id' => 1], 0));
    expect($reconnected)->toHaveCount(1)->and($reconnected[0]->data['html'])->toContain('TunMeNRO')
        ->and($reconnected[0]->data['html'])->toContain('data-nro-respawn');
});

test('SSE sends escaped snapshots with streaming headers and validates limits before streaming', function (): void {
    nroSend(['server_id' => 1, 'content' => '<script>alert("x")</script>', 'occurred_at' => now()->toISOString()])->assertCreated();
    $feed = app(NroNotificationFeedService::class);
    $this->mock(NroNotificationFeedService::class)->shouldReceive('events')->once()->with(['limit' => 2])
        ->andReturnUsing(fn (array $filters) => $feed->events($filters, 0));
    $response = $this->get('/api/nro/notifies/stream?limit=2')->assertOk()->assertStreamed()
        ->assertHeader('Content-Type', 'text/event-stream; charset=utf-8')->assertHeader('X-Accel-Buffering', 'no');
    preg_match('/event: notifications\ndata: ([^\n]+)\n\n/', $response->streamedContent(), $matches);
    $payload = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    expect($payload['count'])->toBe(1)->and($payload['html'])->toContain('&lt;script&gt;')
        ->and($payload['html'])->not->toContain('<script>');
    foreach ([0, 101, -1, 'invalid'] as $limit) {
        $this->getJson('/api/nro/notifies/stream?limit='.$limit)->assertUnprocessable()->assertJsonValidationErrors('limit');
    }
});

test('live snapshots remove expired respawn records even without new notifications', function (): void {
    nroSend(nroSpawn())->assertCreated();
    nroSend(nroDeath())->assertOk();
    $feed = app(NroNotificationFeedService::class);
    $stream = $feed->events(['state' => 'respawning'], 60, 0);
    expect($stream->current()->data['count'])->toBe(1);
    $this->travelTo(now()->setTime(10, 0));
    $stream->next();
    expect($stream->current()->event)->toBe('notifications')->and($stream->current()->data['count'])->toBe(0);
});
