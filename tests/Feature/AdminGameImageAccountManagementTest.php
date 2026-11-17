<?php

use App\Features\Client\GameImage\Services\GameImageOrderService;
use App\Models\GameImageAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->owner = User::factory()->create();
    $this->wallet = Wallet::factory()->create(['user_id' => $this->owner->id, 'balance' => 1000]);
    app(GameImageOrderService::class)->configure(100);
    Storage::fake('local');
    Storage::fake('game-image-uploads');
});

test('only active admins may edit or delete capture accounts', function (): void {
    $account = GameImageAccount::factory()->create(['user_id' => $this->owner->id]);
    $data = ['capture_version' => 1, 'username' => 'edited', 'server' => '3'];
    $this->actingAs($this->owner)->patchJson(route('admin.game-image.update', $account), $data)->assertForbidden();
    $this->deleteJson(route('admin.game-image.destroy', $account), ['capture_version' => 1])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'inactive']))->patchJson(route('admin.game-image.update', $account), $data)->assertForbidden();
    expect($account->fresh()->username)->toBe($account->username);
});

test('admin edits encrypted credentials without changing owner layout fees or returning passwords', function (): void {
    $account = GameImageAccount::factory()->create(['user_id' => $this->owner->id]);
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.update', $account), ['capture_version' => 1, 'username' => 'edited', 'server' => '3', 'password' => 'new-game-secret', 'user_id' => $this->admin->id, 'charged_amount' => 90000])
        ->assertOk()->assertJsonPath('data.username', 'edited')->assertJsonMissingPath('data.password');
    $account->refresh();
    expect($account->password)->toBe('new-game-secret')->and($account->getRawOriginal('password'))->not->toBe('new-game-secret')
        ->and($account->user_id)->toBe($this->owner->id)->and($account->capture_version)->toBe(2)->and($account->charged_amount)->toBe(0);
    $this->patchJson(route('admin.game-image.update', $account), ['capture_version' => 2, 'username' => 'edited-again', 'server' => '3', 'password' => ''])->assertOk();
    expect($account->fresh()->password)->toBe('new-game-secret')->and(WalletTransaction::query()->count())->toBe(0);
});

test('edits reject duplicate accounts processing captures and stale versions', function (): void {
    $account = GameImageAccount::factory()->create(['user_id' => $this->owner->id]);
    $duplicate = GameImageAccount::factory()->create(['user_id' => $this->owner->id]);
    $data = ['capture_version' => 1, 'username' => $duplicate->username, 'server' => $duplicate->server];
    $this->actingAs($this->admin)->patchJson(route('admin.game-image.update', $account), $data)->assertUnprocessable();
    $data['username'] = 'edited';
    $account->forceFill(['status' => 'processing'])->save();
    $this->patchJson(route('admin.game-image.update', $account), $data)->assertConflict();
    $account->forceFill(['status' => 'pending', 'capture_version' => 2])->save();
    $this->patchJson(route('admin.game-image.update', $account), $data)->assertConflict();
    $this->deleteJson(route('admin.game-image.destroy', $account), ['capture_version' => 1])->assertConflict();
    expect($account->fresh())->not->toBeNull();
});

test('deleting unfinished captures refunds exactly once and retains the wallet ledger', function (string $status): void {
    $account = app(GameImageOrderService::class)->create($this->owner, ['username' => 'capture-test', 'password' => 'secret', 'server' => '3']);
    $account->forceFill(['status' => $status])->save();
    expect($this->wallet->fresh()->balance)->toBe(900);
    $this->actingAs($this->admin)->deleteJson(route('admin.game-image.destroy', $account), ['capture_version' => 1])->assertOk();
    expect(GameImageAccount::find($account->id))->toBeNull()->and($this->wallet->fresh()->balance)->toBe(1000)->and(WalletTransaction::query()->count())->toBe(2);
    $this->deleteJson(route('admin.game-image.destroy', $account), ['capture_version' => 1])->assertNotFound();
    expect($this->wallet->fresh()->balance)->toBe(1000);
})->with(['pending', 'processing', 'paused']);

test('deletion removes only this accounts capture files preview and logs and does not refund successful work', function (): void {
    $account = GameImageAccount::factory()->create(['user_id' => $this->owner->id]);
    $directory = 'character/account-'.$account->id.'-v1-11111111-1111-4111-8111-111111111111';
    $preview = 'game-images/'.$account->id.'/previews/preview.png';
    Storage::disk('game-image-uploads')->put($directory.'/panel.png', 'image');
    Storage::disk('game-image-uploads')->put('other/keep.png', 'keep');
    Storage::disk('local')->put($preview, 'preview');
    $account->forceFill(['status' => 'success', 'capture_disk' => 'game-image-uploads', 'capture_directory' => $directory, 'preview_path' => $preview])->save();
    $account->captureLogs()->create(['client_log_id' => (string) Str::uuid(), 'capture_version' => 1, 'message' => 'Done', 'level' => 'info', 'occurred_at' => now()]);
    $this->actingAs($this->admin)->deleteJson(route('admin.game-image.destroy', $account), ['capture_version' => 1])->assertOk();
    Storage::disk('game-image-uploads')->assertMissing($directory.'/panel.png');
    Storage::disk('game-image-uploads')->assertExists('other/keep.png');
    Storage::disk('local')->assertMissing($preview);
    expect($account->captureLogs()->count())->toBe(0)->and(WalletTransaction::query()->count())->toBe(0);
});
