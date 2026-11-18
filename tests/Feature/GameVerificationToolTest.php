<?php

use App\Features\Client\GameVerification\Services\GameVerificationConfigurationService;
use App\Features\Client\GameVerification\Services\GameVerificationGatewayService;
use App\Features\Client\GameVerification\Services\GameVerificationOrderService;
use App\Jobs\VerifyGameAccount;
use App\Models\GameVerificationOrder;
use App\Models\ManagedServer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\EventStreamRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Queue::fake();
    $this->app->instance(EventStreamRuntime::class, new EventStreamRuntime('cli-server'));
    $this->owner = User::factory()->create();
    $this->owner->forceFill(['game_password_captcha_key' => 'ak_owner', 'game_password_captcha_secret' => 'sk_owner',
        'game_password_captcha_profile' => ['username' => 'captcha-owner', 'balance' => '1500', 'checked_at' => now()->toISOString()]])->save();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    ManagedServer::query()->firstOrCreate(['type' => 'web', 'server_code' => 3], ['name' => 'Server 3', 'is_active' => true, 'sort_order' => 3])->update(['is_active' => true]);
    app(GameVerificationConfigurationService::class)->configure(100);
    $this->orders = app(GameVerificationOrderService::class);
    $this->payload = ['username' => 'game-user', 'old_password' => 'private-password', 'server_code' => 3, 'price' => 100, 'request_id' => (string) Str::uuid()];
});

test('verification uses active website servers and rejects game carot and disabled IDs in single and bulk requests', function (): void {
    ManagedServer::factory()->create(['type' => 'web', 'server_id' => '731', 'name' => 'Website verification server']);
    ManagedServer::factory()->create(['type' => 'web', 'server_id' => '732', 'is_active' => false]);
    ManagedServer::factory()->create(['type' => 'game', 'server_id' => '733', 'name' => 'Game only server']);
    ManagedServer::factory()->create(['type' => 'napcarot', 'server_id' => '734', 'name' => 'Carot only server']);
    $this->actingAs($this->owner)->get(route('tools.game-verification'))->assertOk()->assertSee('Website verification server')
        ->assertDontSee('Game only server')->assertDontSee('Carot only server');
    foreach ([732, 733, 734] as $server) {
        $this->postJson(route('tools.game-verification.store'), [...$this->payload, 'server_code' => $server])->assertUnprocessable();
        $this->postJson(route('tools.game-verification.store'), [...$this->payload, 'bulk' => true, 'accounts_text' => 'bulk-user|old-pass|'.$server])->assertUnprocessable();
    }
    expect($this->wallet->fresh()->balance)->toBe(1000)->and(GameVerificationOrder::count())->toBe(0);
    $this->postJson(route('tools.game-verification.store'), [...$this->payload, 'server_code' => 731])->assertRedirect();
    expect(GameVerificationOrder::query()->sole()->server_code)->toBe(731);
});

test('verification page is public with one heading and login required for creating tasks', function (): void {
    $html = $this->get(route('tools.game-verification'))->assertOk()->assertDontSee('name="old_password"', false)->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
    $this->postJson(route('tools.game-verification.store'), $this->payload)->assertUnauthorized();
    $this->actingAs($this->owner)->get(route('tools.game-verification'))->assertOk()->assertSee('captcha-owner')->assertSee('data-verification-sms-modal', false)->assertDontSee('ak_owner')->assertDontSee('sk_owner');
});

test('account creation snapshots encrypted credentials charges once and queues IDs atomically', function (): void {
    $this->actingAs($this->owner)->postJson(route('tools.game-verification.store'), $this->payload)->assertRedirect();
    $order = GameVerificationOrder::query()->sole();
    expect($order->old_password)->toBe('private-password')->and($order->captcha_api_key)->toBe('ak_owner')->and($this->wallet->fresh()->balance)->toBe(900);
    $raw = DB::table('game_verification_orders')->first();
    expect($raw->old_password)->not->toContain('private-password')->and($raw->captcha_api_key)->not->toContain('ak_owner');
    Queue::assertPushed(VerifyGameAccount::class, fn ($job): bool => $job->orderId === $order->id && $job->connection === 'game_verification' && ! str_contains(serialize($job), 'private-password') && ! str_contains(serialize($job), 'ak_owner'));
    $this->postJson(route('tools.game-verification.store'), $this->payload)->assertRedirect();
    expect($this->wallet->fresh()->balance)->toBe(900);
    Queue::assertPushed(VerifyGameAccount::class, 1);
    $this->postJson(route('tools.game-verification.store'), [...$this->payload, 'username' => 'changed-user'])->assertConflict();
});

test('bulk verification validates three fields and rolls back insufficient funds without jobs', function (): void {
    $this->actingAs($this->owner)->postJson(route('tools.game-verification.store'), ['bulk' => true, 'accounts_text' => "one|old-one|sv3\ntwo|old-two|3", 'price' => 100, 'request_id' => (string) Str::uuid()])->assertRedirect();
    expect(GameVerificationOrder::count())->toBe(2)->and($this->wallet->fresh()->balance)->toBe(800);
    $text = implode("\n", array_map(fn ($id) => "bulk-{$id}|old-password|3", range(1, 9)));
    $this->postJson(route('tools.game-verification.store'), ['bulk' => true, 'accounts_text' => $text, 'price' => 100, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
    expect(GameVerificationOrder::count())->toBe(2)->and($this->wallet->fresh()->balance)->toBe(800);
    Queue::assertPushed(VerifyGameAccount::class, 2);
    $this->postJson(route('tools.game-verification.store'), [...$this->payload, 'server_code' => 999999])->assertUnprocessable();
});

test('jobs retain encrypted account password for explicit export and skip duplicate deliveries', function (): void {
    $order = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id, 'username' => 'game-user']);
    $gateway = $this->mock(GameVerificationGatewayService::class);
    $gateway->shouldReceive('verify')->once()->andReturnUsing(function ($username, $password, $server, $progress): string {
        $progress('login_success');
        $progress('extract');

        return 'GO NRO-XT game-user A1b2C3d4';
    });
    $job = new VerifyGameAccount($order->id);
    $job->handle($this->orders, $gateway);
    $job->handle($this->orders, $gateway);
    expect($order->fresh()->status)->toBe('success')->and($order->fresh()->old_password)->toBe('old-secret')
        ->and($order->fresh()->captcha_api_key)->toBeNull()->and($order->fresh()->sms_syntax)->toBe('GO NRO-XT game-user A1b2C3d4');
    expect(json_encode($order->fresh()))->not->toContain('old-secret', 'A1b2C3d4');
    expect(DB::table('game_verification_orders')->value('sms_syntax'))->not->toContain('A1b2C3d4');
});

test('retry is free only on error or pending older than thirty minutes and stale versions cannot finish', function (): void {
    $order = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id, 'username' => 'retry-user', 'status' => 'error']);
    $this->actingAs($this->owner)->postJson(route('tools.game-verification.retry', $order), ['attempt_version' => 1])->assertOk();
    expect($order->fresh()->attempt_version)->toBe(2)->and($this->wallet->fresh()->balance)->toBe(1000);
    $this->orders->start($order->id, 2);
    $this->orders->finish($order->id, 'success', attemptVersion: 1, syntax: 'GO NRO-XT retry-user A1b2C3d4');
    expect($order->fresh()->status)->toBe('processing');
    $this->postJson(route('tools.game-verification.retry', $order), ['attempt_version' => 2])->assertConflict();
    $order->forceFill(['status' => 'pending', 'queued_at' => now()->subMinutes(30)])->save();
    $this->postJson(route('tools.game-verification.retry', $order), ['attempt_version' => 2])->assertConflict();
    $order->forceFill(['queued_at' => now()->subMinutes(31)])->save();
    $this->postJson(route('tools.game-verification.retry', $order), ['attempt_version' => 2])->assertOk();
    expect(WalletTransaction::count())->toBe(0);
});

test('owner scoped exports support SMS lists and password format but reject foreign or unfinished accounts', function (): void {
    $one = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id, 'username' => 'one', 'status' => 'success', 'sms_syntax' => 'GO NRO-XT one A1b2C3d4']);
    $two = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id, 'username' => 'two', 'status' => 'success', 'sms_syntax' => 'GO NRO-XT two E5f6G7h8']);
    $foreign = GameVerificationOrder::factory()->create();
    $pending = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id]);
    $url = route('tools.game-verification.export');
    $this->actingAs($this->owner)->postJson($url, ['ids' => [$two->id, $one->id], 'format' => 'sms'])->assertOk()->assertJsonPath('data.0.syntax', 'GO NRO-XT two E5f6G7h8')->assertDontSee('old-secret');
    $this->postJson($url, ['ids' => [$one->id], 'format' => 'password', 'new_password' => 'new-secret'])->assertOk()->assertJsonPath('data.0.line', 'one|old-secret|3|new-secret');
    $this->postJson($url, ['ids' => [$one->id, $foreign->id], 'format' => 'sms'])->assertForbidden();
    $this->postJson($url, ['ids' => [$pending->id], 'format' => 'sms'])->assertConflict();
    $this->postJson($url, ['ids' => [$one->id], 'format' => 'password'])->assertUnprocessable();
    $this->postJson($url, ['ids' => [$one->id, $one->id], 'format' => 'sms'])->assertUnprocessable();
});

test('SSE is owner scoped paginated and excludes account passwords and captcha keys', function (): void {
    GameVerificationOrder::factory()->count(21)->create(['user_id' => $this->owner->id]);
    GameVerificationOrder::factory()->create(['username' => 'foreign-account']);
    $this->actingAs($this->owner)->getJson(route('tools.game-verification.history'))->assertOk()->assertDontSee('old-secret')->assertDontSee('ak_test')->assertDontSee('foreign-account');
    $stream = $this->get(route('tools.game-verification.stream', ['page' => 2]));
    expect($stream->streamedContent())->toContain('event: orders', 'data-verification-order')->not->toContain('foreign-account', 'sk_test', 'old-secret');
});

test('admin configuration history and cleanup protect roles wallet ledger and replayed requests', function (): void {
    $this->actingAs($this->owner)->getJson(route('admin.game-verification.configuration'))->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->putJson(route('admin.game-verification.configure'), ['price' => -1])->assertUnprocessable();
    $this->putJson(route('admin.game-verification.configure'), ['price' => 0])->assertOk()->assertJsonPath('data.price', 0);
    app(GameVerificationConfigurationService::class)->configure(100);
    $this->actingAs($this->owner)->postJson(route('tools.game-verification.store'), $this->payload)->assertRedirect();
    $order = GameVerificationOrder::query()->sole();
    $this->orders->finish($order->id, 'error', 'LOGIN_FAILED', 1);
    $pending = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id]);
    $transactions = WalletTransaction::count();
    $this->actingAs($admin)->getJson(route('admin.game-verification.history'))->assertOk()->assertDontSee('private-password')->assertDontSee('ak_owner');
    $this->deleteJson(route('admin.game-verification.clear'), ['ids' => [$order->id, $pending->id]])->assertOk()->assertJsonPath('data.deleted', 1)->assertJsonPath('data.skipped', 1);
    expect(WalletTransaction::count())->toBe($transactions)->and($this->wallet->fresh()->balance)->toBe(900);
    $this->actingAs($this->owner)->postJson(route('tools.game-verification.store'), $this->payload)->assertConflict();
    expect(GameVerificationOrder::whereKey($pending->id)->exists())->toBeTrue();
});

test('verification SEO uses the shared article FAQ schemas and login hiding controls', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    $endpoint = '/api/admin-api/settings/services/account_verification';
    $this->getJson($endpoint)->assertOk()->assertJsonPath('data.seo.slug', 'xac-minh-tai-khoan-game-ngoc-rong-online');
    $payload = ['title' => 'Hướng dẫn xác minh NRO', 'excerpt' => 'Lấy cú pháp và gửi tin nhắn.',
        'content' => [['type' => 'h2', 'children' => [['text' => 'Cách gửi SMS xác minh']]], ['type' => 'paragraph', 'children' => [['text' => 'Nội dung SEO xác minh riêng.']]]],
        'faq' => [['question' => 'Gửi tới đâu?', 'answer' => 'Mở SMS tới 6020.']],
        'seo_title' => 'Xác minh tài khoản NRO', 'seo_description' => 'Lấy cú pháp gửi 6020', 'language' => 'vi', 'robots' => 'index,follow',
        'article_schema' => true, 'breadcrumb_schema' => true, 'is_published' => true, 'hide_when_authenticated' => true, 'expected_version' => 0];
    $this->patchJson($endpoint.'/seo', $payload)->assertOk();
    $this->get(route('tools.game-verification'))->assertOk()->assertDontSee('data-tool-guide', false);
    auth()->forgetGuards();
    $html = $this->get(route('tools.game-verification'))->assertOk()->assertSee('Nội dung SEO xác minh riêng.')
        ->assertSee('data-seo-toc-toggle', false)->assertSee('data-seo-faq', false)
        ->assertSee('"@type":"FAQPage"', false)->assertSee('"@type":"BreadcrumbList"', false)->getContent();
    expect(preg_match_all('/<h1\b/i', $html))->toBe(1);
});

test('admin configuration and cleanup protect active orders wallet records and replay tombstones', function (): void {
    $this->actingAs($this->owner)->getJson(route('admin.game-verification.configuration'))->assertForbidden();
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->putJson(route('admin.game-verification.configure'), ['price' => 0])->assertOk()->assertJsonPath('data.price', 0);
    $done = GameVerificationOrder::factory()->create(['user_id' => $this->owner->id, 'status' => 'success', 'completed_at' => now()->subDays(40)]);
    $pending = GameVerificationOrder::factory()->create();
    $this->deleteJson(route('admin.game-verification.clear'), ['ids' => [$done->id, $pending->id]])->assertOk()->assertJsonPath('data.deleted', 1);
    expect($pending->fresh())->not->toBeNull()->and(DB::table('game_verification_pruned_requests')->where('request_id', $done->request_id)->exists())->toBeTrue();
    $this->getJson(route('admin.game-verification.history'))->assertOk()->assertDontSee('old-secret');
});
