<?php

use App\Models\GameImageAccount;
use App\Models\User;

function sharedGameAccount(): GameImageAccount
{
    return GameImageAccount::factory()->create(['username' => 'private-login', 'password' => 'private-password', 'layout' => ['item_ids' => [], 'show_details' => true, 'positions' => [], 'groups' => []]]);
}

test('sharing publishes a read only snapshot without account credentials', function (): void {
    $account = sharedGameAccount();
    $url = $this->actingAs(User::findOrFail($account->user_id))->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    expect($account->fresh()->share_token)->toHaveLength(64);
    $snapshot = $account->layout;
    $account->update(['layout' => [...$snapshot, 'item_ids' => [1]]]);
    auth()->forgetGuards();
    $this->get($url)->assertOk()->assertSee('data-read-only="true"', false)
        ->assertViewHas('account', fn (array $data): bool => $data['layout'] === $snapshot && ! isset($data['update_url']))
        ->assertDontSee('private-login')->assertDontSee('private-password')->assertHeader('Referrer-Policy', 'no-referrer');
});

test('only the owner can create or revoke a share', function (): void {
    $account = sharedGameAccount();
    $this->postJson(route('tools.game-image.share', $account))->assertUnauthorized();
    $this->actingAs(User::factory()->create())->postJson(route('tools.game-image.share', $account))->assertForbidden();
    $this->deleteJson(route('tools.game-image.share.revoke', $account))->assertForbidden();
    expect($account->fresh()->share_token)->toBeNull();
});

test('share links rotate and can be revoked', function (): void {
    $account = sharedGameAccount();
    $this->actingAs(User::findOrFail($account->user_id));
    $old = $this->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    $current = $this->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    $this->get($old)->assertNotFound();
    $this->get($current)->assertOk();
    $this->deleteJson(route('tools.game-image.share.revoke', $account))->assertOk();
    $this->get($current)->assertNotFound();
    expect($account->fresh()->shared_layout)->toBeNull();
});

test('unsigned tampered and expired share links are rejected', function (): void {
    $account = sharedGameAccount();
    $url = $this->actingAs(User::findOrFail($account->user_id))->postJson(route('tools.game-image.share', $account))->assertOk()->json('url');
    $this->get(strtok($url, '?'))->assertForbidden();
    $this->get($url.'&changed=1')->assertForbidden();
    $this->travel(8)->days();
    $this->get($url)->assertForbidden();
});

test('studio layout normalizes layer controls and rejects external images', function (): void {
    $account = sharedGameAccount();
    $this->actingAs(User::findOrFail($account->user_id));
    $layout = [...$account->layout, 'layers' => ['bag' => ['hidden' => '0', 'locked' => '1', 'scale' => '1.5', 'rotation' => '90', 'opacity' => '0.5']]];
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertOk()
        ->assertJsonPath('data.layout.layers.bag.hidden', false)->assertJsonPath('data.layout.layers.bag.locked', true)
        ->assertJsonPath('data.layout.layers.bag.scale', 1.5);
    $layout['images'] = [['id' => 'image-1', 'name' => 'unsafe', 'src' => 'https://example.com/image.png', 'width' => 100, 'height' => 100]];
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertUnprocessable()->assertJsonValidationErrors('layout.images.0.src');
    $layout['images'][0]['src'] = 'data:image/png;base64,aGVsbG8=';
    $this->patchJson(route('tools.game-image.update', $account), ['layout' => $layout])->assertUnprocessable()->assertJsonValidationErrors('layout.images.0.src');
});
