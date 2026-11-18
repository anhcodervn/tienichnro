<?php

use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Features\Client\GameImage\Services\GameImageToolService;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Database\Seeders\ManagedServerSeeder;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(ManagedServerSeeder::class);
    $this->owner = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 20000]);
    $this->orders = app(GameImageOrderService::class);
    $this->orders->configure(10000);
    $this->account = $this->orders->create($this->owner, ['username' => 'game-user', 'password' => 'game-secret', 'server' => '3']);
});

test('cancellation retains 1000 from the original fee and refunds the remainder exactly once', function (string $status): void {
    $this->account->forceFill(['status' => $status, 'capture_claim_hash' => hash('sha256', 'claim')])->save();
    $data = ['action' => 'cancel', 'capture_version' => 1, 'refund' => 20000, 'fee' => 0];
    $this->actingAs($this->owner)->postJson(route('tools.game-image.control', $this->account), $data)->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.refunded_amount', 9000);
    $this->postJson(route('tools.game-image.control', $this->account), $data)->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(19000)
        ->and(WalletTransaction::query()->where('direction', 'debit')->count())->toBe(1)
        ->and(WalletTransaction::query()->where('direction', 'credit')->sum('amount'))->toBe(9000)
        ->and($this->account->fresh()->capture_claim_hash)->toBeNull();
    $html = $this->get(route('tools.game-image'))->assertOk()->getContent();
    expect($html)->toContain('9.000', '1.000')->not->toContain('data-game-image-order-control="cancel"');
})->with(['pending', 'processing', 'paused']);

test('low or free capture fees never create an extra debit or a negative refund', function (int $price): void {
    $this->orders->configure($price);
    $account = $this->orders->create($this->owner, ['username' => 'small-fee', 'password' => 'secret', 'server' => '3']);
    $before = $this->wallet->fresh()->balance;
    $debits = WalletTransaction::query()->where('direction', 'debit')->count();
    $this->actingAs($this->owner)->postJson(route('tools.game-image.control', $account), ['action' => 'cancel', 'capture_version' => 1])->assertOk()->assertJsonPath('data.refunded_amount', 0);
    expect($this->wallet->fresh()->balance)->toBe($before)->and(WalletTransaction::query()->where('direction', 'debit')->count())->toBe($debits);
})->with([0, 500, 1000]);

test('pause holds the paid capture lets its owner replace credentials and resumes without another charge', function (): void {
    $token = (string) Str::uuid();
    $this->account->forceFill(['status' => 'processing', 'capture_claim_hash' => hash('sha256', $token), 'capture_public_key' => 'public-key'])->save();
    $route = route('tools.game-image.control', $this->account);
    $this->actingAs($this->owner)->postJson($route, ['action' => 'pause', 'capture_version' => 1])->assertOk()->assertJsonPath('data.status', 'paused')->assertJsonPath('data.capture_version', 2);
    $paused = $this->account->fresh();
    expect($paused->charged_amount)->toBe(10000)->and($paused->capture_claim_hash)->toBeNull()->and($paused->capture_public_key)->toBeNull()->and($paused->refunded_at)->toBeNull();
    expect(fn () => app(GameImageToolService::class)->assertClaim($paused, 1, $token))->toThrow(HttpException::class);
    $this->postJson($route, ['action' => 'resume', 'capture_version' => 1])->assertConflict();
    $this->patchJson(route('tools.game-image.details', $this->account), ['username' => 'replacement', 'password' => 'replacement-secret', 'server' => '6', 'capture_version' => 2])->assertOk();
    $this->postJson($route, ['action' => 'resume', 'capture_version' => 2])->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.capture_version', 3);
    expect($this->account->fresh()->username)->toBe('replacement')->and($this->account->fresh()->password)->toBe('replacement-secret')
        ->and($this->wallet->fresh()->balance)->toBe(10000)->and(WalletTransaction::query()->count())->toBe(1);
    $this->postJson($route, ['action' => 'cancel', 'capture_version' => 3])->assertOk();
    expect($this->wallet->fresh()->balance)->toBe(19000);
});

test('a cancelled account starts a separately paid recapture with reset refund fields', function (): void {
    $cancelled = $this->orders->control($this->owner, $this->account, 'cancel', 1);
    $next = $this->orders->recapture($cancelled, 1, 10000);
    expect($next->status)->toBe('pending')->and($next->capture_version)->toBe(2)
        ->and($next->refunded_amount)->toBeNull()->and($next->refunded_at)->toBeNull()
        ->and($this->wallet->fresh()->balance)->toBe(9000);
});

test('owner controls reject strangers inactive users final orders and stale versions', function (): void {
    $route = route('tools.game-image.control', $this->account);
    $data = ['action' => 'cancel', 'capture_version' => 1];
    $this->postJson($route, $data)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson($route, $data)->assertForbidden();
    $this->owner->update(['status' => 'inactive']);
    $this->actingAs($this->owner)->postJson($route, $data)->assertForbidden();
    $this->owner->update(['status' => 'active']);
    $this->actingAs($this->owner)->postJson($route, ['action' => 'cancel', 'capture_version' => 2])->assertConflict();
    foreach (['success', 'error'] as $status) {
        $this->account->forceFill(['status' => $status])->save();
        $this->postJson($route, $data)->assertConflict();
        $this->postJson($route, ['action' => 'pause', 'capture_version' => 1])->assertConflict();
    }
    expect($this->wallet->fresh()->balance)->toBe(10000);
});

test('paused and cancelled accounts are excluded from tool pending feed but remain filterable in owner history', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    foreach (['paused', 'cancelled'] as $status) {
        $this->account->forceFill(['status' => $status])->save();
        $this->actingAs($admin)->getJson(route('api.game-image.pending'))->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->owner)->get(route('tools.game-image', ['status' => $status]))->assertOk()->assertViewHas('accounts', fn ($accounts): bool => $accounts->total() === 1);
    }
});
