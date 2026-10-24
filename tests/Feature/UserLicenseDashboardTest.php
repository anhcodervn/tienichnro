<?php

use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicensePlan;
use App\Models\User;

test('license dashboard requires authentication', function (): void {
    $this->get(route('account.profile.dashboard'))->assertRedirect(route('login'));
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('dashboard entry redirects account roles to their license dashboard', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('account.profile.dashboard'));
    $this->followingRedirects()->get('/dashboard')->assertOk()->assertSee('Dashboard license');
})->with([User::ROLE_USER, User::ROLE_ADMIN]);

test('account roles have dashboard navigation and an empty license state', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('account.index'))
        ->assertOk()->assertSee(route('account.profile.dashboard'), false);
    $this->get(route('account.profile.dashboard'))->assertOk()
        ->assertSee('Bạn chưa được cấp license')
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([User::ROLE_USER, User::ROLE_ADMIN]);

test('dashboard shows only owned licenses and handles legacy keys and expiration', function (): void {
    $user = User::factory()->create();
    $owned = License::factory()->create(['user_id' => $user->id, 'encrypted_key' => 'MY-PRIVATE-LICENSE', 'status' => 'active', 'activated_at' => now()->subDays(31), 'expires_at' => now()->subDay()]);
    $device = LicenseDevice::factory()->create(['license_id' => $owned->id, 'device_name' => 'MY-LAPTOP']);
    $owned->update(['current_device_uuid' => $device->device_uuid]);
    License::factory()->create(['user_id' => $user->id, 'encrypted_key' => null, 'key_prefix' => 'LEGACYKEY']);
    License::factory()->create(['user_id' => $user->id, 'duration_days' => null]);
    License::factory()->create(['user_id' => User::factory()->create()->id, 'encrypted_key' => 'FOREIGN-SECRET', 'key_prefix' => 'FOREIGNKEY']);
    License::factory()->create(['user_id' => null, 'encrypted_key' => 'UNASSIGNED-SECRET']);

    $this->actingAs($user)->get(route('account.profile.dashboard'))->assertOk()
        ->assertSee('MY-PRIVATE-LICENSE')->assertSee('MY-LAPTOP')
        ->assertSee('Đã hết hạn')->assertSee('LEGACYKEY')->assertSee('Vĩnh viễn')
        ->assertSee('data-license-copy', false)
        ->assertDontSee('FOREIGN-SECRET')->assertDontSee('FOREIGNKEY')->assertDontSee('UNASSIGNED-SECRET')
        ->assertDontSee($owned->key_hash)->assertDontSee('test-key');
});

test('license issued and reassigned by admin appears only on its current owner dashboard', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $owner = User::factory()->create();
    $nextOwner = User::factory()->create();
    $plan = LicensePlan::factory()->create();
    $issued = $this->actingAs($admin)->postJson('/api/admin-api/license/keys', [
        'plan_id' => $plan->id, 'quantity' => 1, 'user_id' => $owner->id,
    ])->assertCreated()->json('data.0');

    $this->actingAs($owner)->get(route('account.profile.dashboard'))->assertOk()->assertSee($issued['key']);
    $this->postJson('/api/admin-api/license/keys', ['plan_id' => $plan->id, 'quantity' => 1])->assertForbidden();
    $this->patchJson('/api/admin-api/license/keys/'.$issued['id'], ['action' => 'edit', 'user_id' => $nextOwner->id])->assertForbidden();

    $this->actingAs($admin)->patchJson('/api/admin-api/license/keys/'.$issued['id'], [
        'action' => 'edit', 'user_id' => $nextOwner->id, 'duration_days' => $plan->duration_days,
        'expires_at' => null, 'transfer_cooldown' => $plan->transfer_cooldown, 'reason' => 'Assign to another customer',
    ])->assertOk();
    $this->actingAs($owner)->get(route('account.profile.dashboard'))->assertOk()->assertDontSee($issued['key']);
    $this->actingAs($nextOwner)->get(route('account.profile.dashboard'))->assertOk()->assertSee($issued['key']);
});

test('license dashboard paginates owned licenses', function (): void {
    $user = User::factory()->create();
    $plan = LicensePlan::factory()->create();
    License::factory()->count(13)->create(['user_id' => $user->id, 'plan_id' => $plan->id]);

    $this->actingAs($user)->get(route('account.profile.dashboard'))->assertOk()
        ->assertViewHas('licenses', fn ($licenses): bool => $licenses->total() === 13 && $licenses->count() === 12);
    $this->get(route('account.profile.dashboard', ['page' => 2]))->assertOk()
        ->assertViewHas('licenses', fn ($licenses): bool => $licenses->count() === 1);
});

test('license cards collapse details and place copy rotation and more in the same action row', function (): void {
    $user = User::factory()->create();
    $license = License::factory()->create(['user_id' => $user->id, 'encrypted_key' => 'COMPACT-KEY']);
    $revoked = License::factory()->create(['user_id' => $user->id, 'status' => 'revoked', 'encrypted_key' => 'REVOKED-KEY']);
    $response = $this->actingAs($user)->get(route('account.profile.dashboard'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
    $xpath = new DOMXPath($document);
    $actions = '//div[@data-license-actions][button[@data-license-copy="license-key-'.$license->id.'"]]';

    expect($xpath->query($actions.'//button')->length)->toBe(3)
        ->and($xpath->query($actions.'/form[@data-license-rotate]/button[@type="submit"]')->length)->toBe(1)
        ->and($xpath->query($actions.'/button[@aria-controls="license-details-'.$license->id.'" and @aria-expanded="false"]')->length)->toBe(1)
        ->and($xpath->query('//div[@id="license-details-'.$license->id.'" and @hidden]/dl')->length)->toBe(1)
        ->and($xpath->query($actions.'/form/input[@name="confirm_rotation" and @value="0"]')->length)->toBe(1)
        ->and($xpath->query('//form[@action="'.route('account.profile.licenses.rotate', $revoked).'"]')->length)->toBe(0);
});
