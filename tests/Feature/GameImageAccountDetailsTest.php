<?php

use App\Models\GameImageAccount;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\ManagedServerSeeder;

beforeEach(function (): void {
    $this->seed(ManagedServerSeeder::class);
});

test('owners can correct capture credentials without charges and passwords never appear in the response', function (): void {
    $user = User::factory()->create();
    $account = GameImageAccount::factory()->create(['user_id' => $user->id, 'layout' => ['item_ids' => [], 'show_details' => true, 'positions' => []]]);
    $layout = $account->layout;
    $this->actingAs($user)->patchJson(route('tools.game-image.details', $account), ['capture_version' => 1, 'username' => 'corrected', 'server' => '3', 'password' => 'correct-secret', 'charged_amount' => 999, 'user_id' => 999, 'status' => 'success'])
        ->assertOk()->assertJsonPath('data.username', 'corrected')->assertJsonPath('data.capture_version', 2)->assertJsonMissingPath('data.password');
    $account->refresh();
    expect($account->password)->toBe('correct-secret')->and($account->getRawOriginal('password'))->not->toBe('correct-secret')
        ->and($account->status)->toBe('pending')->and($account->user_id)->toBe($user->id)->and($account->layout)->toBe($layout)
        ->and(WalletTransaction::query()->count())->toBe(0);
    $this->patchJson(route('tools.game-image.details', $account), ['capture_version' => 2, 'username' => 'corrected', 'server' => '6', 'password' => ''])->assertOk();
    expect($account->fresh()->password)->toBe('correct-secret');
    $html = $this->get(route('tools.game-image'))->assertOk()->getContent();
    expect($html)->toContain('data-game-image-edit-details', 'data-game-image-details-modal')->not->toContain('correct-secret');
});

test('visitors other owners and inactive users cannot edit account details', function (): void {
    $account = GameImageAccount::factory()->create();
    $data = ['capture_version' => 1, 'username' => 'changed', 'server' => '3'];
    $this->patchJson(route('tools.game-image.details', $account), $data)->assertUnauthorized();
    $this->actingAs(User::factory()->create())->patchJson(route('tools.game-image.details', $account), $data)->assertForbidden();
    $owner = $account->user;
    $owner->update(['status' => 'inactive']);
    $this->actingAs($owner)->patchJson(route('tools.game-image.details', $account), $data)->assertForbidden();
    expect($account->fresh()->username)->toBe($account->username);
});

test('client edits reject active captures stale snapshots and duplicate accounts', function (): void {
    $user = User::factory()->create();
    $account = GameImageAccount::factory()->create(['user_id' => $user->id]);
    $other = GameImageAccount::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user)->patchJson(route('tools.game-image.details', $account), ['capture_version' => 1, 'username' => $other->username, 'server' => $other->server])->assertUnprocessable();
    $data = ['capture_version' => 1, 'username' => 'changed', 'server' => '3'];
    $account->forceFill(['status' => 'processing'])->save();
    $this->patchJson(route('tools.game-image.details', $account), $data)->assertConflict();
    $account->forceFill(['status' => 'pending', 'capture_version' => 2])->save();
    $this->patchJson(route('tools.game-image.details', $account), $data)->assertConflict();
    expect($account->fresh()->username)->toBe($account->username)->and(WalletTransaction::query()->count())->toBe(0);
});
