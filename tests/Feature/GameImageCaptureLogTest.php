<?php

use App\Features\Client\GameImage\Services\GameImageAccountFeedService;
use App\Features\Client\GameImage\Services\GameImageAccountListService;
use App\Models\GameImageAccount;
use App\Models\GameImageCaptureLog;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function captureLogEntry(array $extra = []): array
{
    return [...['client_log_id' => (string) Str::uuid(), 'level' => 'info', 'step' => 'login', 'message' => 'Connecting to game', 'occurred_at' => '2026-11-16T10:00:00+07:00'], ...$extra];
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->claim = (string) Str::uuid();
    $this->account = GameImageAccount::factory()->create(['user_id' => $this->owner->id, 'password' => 'never-log-game-password', 'status' => 'processing', 'capture_claim_hash' => hash('sha256', $this->claim)]);
    $this->payload = ['capture_version' => 1, 'claim_token' => $this->claim, 'logs' => [captureLogEntry()]];
    $this->withToken($this->admin->createToken('log-tool')->plainTextToken);
});

test('private batch logs are idempotent and first write wins even when a retry changes content', function (): void {
    $url = route('api.game-image.logs.store', $this->account);
    $this->payload['logs'][] = captureLogEntry(['step' => 'capture', 'message' => 'Captured quest panel']);
    $this->postJson($url, $this->payload)->assertOk()->assertJsonPath('data.inserted', 2)->assertJsonPath('data.duplicates', 0)->assertHeader('Cache-Control', 'no-store, private');
    $this->payload['logs'][0]['message'] = 'Changed retry';
    $this->postJson($url, $this->payload)->assertOk()->assertJsonPath('data.inserted', 0)->assertJsonPath('data.duplicates', 2);
    expect(GameImageCaptureLog::query()->count())->toBe(2)
        ->and(GameImageCaptureLog::query()->oldest('id')->first()->message)->toBe('Connecting to game');
    $this->getJson(route('api.game-image.logs.index', $this->account))->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.occurred_at', '2026-11-16T03:00:00.000000Z')->assertJsonPath('meta.capture_version', 1)
        ->assertDontSee($this->claim)->assertDontSee('never-log-game-password');
});

test('only active admin tools write or read private API logs', function (): void {
    foreach ([null, $this->owner, User::factory()->create(['role' => 'admin', 'status' => 'banned'])] as $user) {
        auth()->forgetGuards();
        $this->withHeader('Authorization', $user ? 'Bearer '.$user->createToken('bad-tool')->plainTextToken : '');
        $post = $this->postJson(route('api.game-image.logs.store', $this->account), $this->payload);
        $get = $this->getJson(route('api.game-image.logs.index', $this->account));
        $user ? $post->assertForbidden() : $post->assertUnauthorized();
        $user ? $get->assertForbidden() : $get->assertUnauthorized();
    }
    expect(GameImageCaptureLog::query()->count())->toBe(0);
});

test('claims versions capture state and owner activity are checked before log writes', function (): void {
    $url = route('api.game-image.logs.store', $this->account);
    $this->postJson($url, [...$this->payload, 'claim_token' => (string) Str::uuid()])->assertConflict();
    $this->postJson($url, [...$this->payload, 'capture_version' => 2])->assertConflict();
    $this->account->forceFill(['status' => 'pending'])->save();
    $this->postJson($url, $this->payload)->assertConflict();
    $this->account->forceFill(['status' => 'processing'])->save();
    $this->owner->update(['status' => 'banned']);
    $this->postJson($url, $this->payload)->assertConflict();
    expect(GameImageCaptureLog::query()->count())->toBe(0);
});

test('terminal captures can append final logs without changing status or wallet', function (): void {
    $wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    foreach (['success', 'error'] as $status) {
        $this->account->forceFill(['status' => $status])->save();
        $this->postJson(route('api.game-image.logs.store', $this->account), [...$this->payload, 'logs' => [captureLogEntry(['level' => 'error'])]])->assertOk();
        expect($this->account->fresh()->status)->toBe($status)->and($wallet->fresh()->balance)->toBe(1000)
            ->and($this->account->fresh()->refunded_at)->toBeNull();
    }
});

test('bad batches are rejected atomically', function (string $case): void {
    $entry = captureLogEntry();
    $logs = match ($case) {
        'empty' => [], 'large' => array_fill(0, 51, $entry), 'duplicate' => [$entry, $entry],
        'uuid' => [[...$entry, 'client_log_id' => 'bad']], 'level' => [[...$entry, 'level' => 'fatal']],
        'message' => [$entry, [...captureLogEntry(), 'message' => str_repeat('x', 1001)]],
        'time' => [[...$entry, 'occurred_at' => '9999-01-01']],
        'extra' => [[...$entry, 'password' => 'do-not-accept']],
    };
    $this->postJson(route('api.game-image.logs.store', $this->account), [...$this->payload, 'logs' => $logs])->assertUnprocessable();
    expect(GameImageCaptureLog::query()->count())->toBe(0);
})->with(['empty', 'large', 'duplicate', 'uuid', 'level', 'message', 'time', 'extra']);

test('read cursors preserve order and scope history to the requested account and version', function (): void {
    $logs = GameImageCaptureLog::factory()->count(3)->create(['account_id' => $this->account->id]);
    GameImageCaptureLog::factory()->create(['message' => 'other-owner-log']);
    $url = route('api.game-image.logs.index', $this->account);
    $first = $this->getJson($url.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true)->assertDontSee('other-owner-log');
    $this->getJson($url.'?after_id='.$first->json('meta.next_after_id'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $logs->last()->id)->assertJsonPath('meta.has_more', false);
    $this->account->forceFill(['capture_version' => 2])->save();
    $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson($url.'?capture_version=1')->assertOk()->assertJsonCount(3, 'data');
    $this->getJson($url.'?capture_version=3')->assertUnprocessable();
    $this->getJson($url.'?per_page=101')->assertUnprocessable();
    $this->withHeader('Authorization', '')->actingAs($this->owner)->getJson(route('tools.game-image.logs', $this->account))->assertOk();
    $this->actingAs(User::factory()->create())->getJson(route('tools.game-image.logs', $this->account))->assertForbidden();
});

test('secrets are redacted and realtime snapshots show only current capture logs with HTML escaped', function (): void {
    $url = route('api.game-image.logs.store', $this->account);
    $feed = app(GameImageAccountFeedService::class);
    $before = $feed->snapshot($this->owner, []);
    $this->postJson($url, [...$this->payload, 'logs' => [captureLogEntry(['step' => 'never-log-game-password', 'message' => '<script>bad()</script> never-log-game-password '.$this->claim])]])->assertOk();
    $log = GameImageCaptureLog::query()->sole();
    expect($log->message)->toContain('[REDACTED]')->not->toContain('never-log-game-password', $this->claim);
    $after = $feed->snapshot($this->owner, []);
    expect($after['signature'])->not->toBe($before['signature'])
        ->and($after['html'])->toContain('&lt;script&gt;bad()&lt;/script&gt;')->not->toContain('<script>bad()</script>', 'never-log-game-password');
    $oldId = $this->payload['logs'][0]['client_log_id'];
    $newClaim = (string) Str::uuid();
    $this->account->forceFill(['capture_version' => 2, 'capture_claim_hash' => hash('sha256', $newClaim)])->save();
    expect($feed->snapshot($this->owner, [])['html'])->not->toContain('bad()');
    $this->postJson($url, $this->payload)->assertConflict();
    $this->postJson($url, ['capture_version' => 2, 'claim_token' => $newClaim, 'logs' => [captureLogEntry(['client_log_id' => $oldId])]])->assertOk()->assertJsonPath('data.inserted', 1);
});

test('capture log rows use a fixed query count across a full account page and cascade on deletion', function (): void {
    $accounts = GameImageAccount::factory()->count(19)->create(['user_id' => $this->owner->id]);
    foreach ($accounts->push($this->account) as $account) {
        GameImageCaptureLog::factory()->create(['account_id' => $account->id]);
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $page = app(GameImageAccountListService::class)->paginate($this->owner, []);
    foreach ($page as $account) {
        expect($account->captureLogs)->toHaveCount(1);
    }
    expect(DB::getQueryLog())->toHaveCount(3);
    DB::disableQueryLog();
    $this->account->delete();
    expect(GameImageCaptureLog::query()->where('account_id', $this->account->id)->count())->toBe(0);
});

test('capture textarea displays the current run messages in order without raw step codes', function (): void {
    $this->account->forceFill(['capture_version' => 2])->save();
    GameImageCaptureLog::factory()->create(['account_id' => $this->account->id, 'message' => 'Log của lượt cũ']);
    $messages = ['Đang mở game.', 'Đăng nhập game thành công.', 'Đang tới map 102.', 'Đã chụp ảnh hành trang thành công.', 'Đã hoàn tất.'];
    foreach ($messages as $message) {
        GameImageCaptureLog::factory()->create(['account_id' => $this->account->id, 'capture_version' => 2,
            'step' => 'raw_travel_code', 'message' => $message]);
    }

    $html = app(GameImageAccountFeedService::class)->snapshot($this->owner, [])['html'];
    expect($html)->toContain('<textarea readonly', 'data-game-image-capture-log="'.$this->account->id.':2"')
        ->not->toContain('Log của lượt cũ', 'raw_travel_code');
    preg_match('~<textarea[^>]*>(.*?)</textarea>~s', $html, $textarea);
    expect(array_map(fn ($line) => substr($line, 11), explode("\n", $textarea[1])))->toBe($messages);
});

test('existing SSE sends a changed table when logs arrive and a heartbeat for duplicate retries', function (): void {
    $events = app(GameImageAccountFeedService::class)->events($this->owner, [], 5, 0);
    expect($events->current()->event)->toBe('accounts');
    $url = route('api.game-image.logs.store', $this->account);
    $this->postJson($url, $this->payload)->assertOk();
    $events->next();
    expect($events->current()->event)->toBe('accounts')->and($events->current()->data['html'])->toContain('Connecting to game');
    $this->postJson($url, $this->payload)->assertOk();
    $events->next();
    expect($events->current()->event)->toBe('heartbeat');
});
