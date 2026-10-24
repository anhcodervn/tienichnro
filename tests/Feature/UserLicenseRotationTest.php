<?php

use App\Features\Client\Profile\Actions\RotateLicenseAction;
use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicenseDevice;
use App\Models\LicenseSession;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

test('owners can rotate their key while retaining license terms', function (string $role): void {
    $owner = User::factory()->create(['role' => $role]);
    $oldKey = 'OLD-OWNER-KEY';
    $license = License::factory()->create([
        'user_id' => $owner->id, 'encrypted_key' => $oldKey,
        'key_hash' => LicenseSessionService::hashKey($oldKey), 'status' => 'active',
        'activated_at' => now()->subDay(), 'expires_at' => now()->addDays(29), 'generation' => 2,
    ]);
    $device = LicenseDevice::factory()->create(['license_id' => $license->id]);
    $license->update(['current_device_uuid' => $device->device_uuid]);
    $session = LicenseSession::factory()->create(['license_id' => $license->id, 'device_id' => $device->id, 'generation' => 2]);
    $terms = $license->only(['user_id', 'plan_id', 'product_id', 'duration_days', 'activated_at', 'expires_at', 'status', 'transfer_cooldown']);

    $this->actingAs($owner)->get(route('account.profile.dashboard'))->assertOk()
        ->assertSee(route('account.profile.licenses.rotate', $license), false)->assertSee('data-license-rotate', false);
    $this->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1', 'user_id' => null, 'duration_days' => null])
        ->assertRedirect(route('account.profile.dashboard'))->assertSessionHas('success');

    $license->refresh();
    expect($license->encrypted_key)->not->toBe($oldKey)
        ->and($license->key_hash)->toBe(LicenseSessionService::hashKey($license->encrypted_key))
        ->and($license->key_prefix)->toBe(substr($license->encrypted_key, 0, 9))
        ->and($license->only(array_keys($terms)))->toEqual($terms)
        ->and($license->generation)->toBe(3)
        ->and($license->current_device_uuid)->toBeNull()
        ->and($device->fresh()->status)->toBe('inactive')
        ->and($session->fresh()->status)->toBe('revoked');
    $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'actor_id' => $owner->id, 'event' => 'rotate']);
    $this->get(route('account.profile.dashboard'))->assertOk()->assertSee($license->encrypted_key)->assertDontSee($oldKey)
        ->assertHeader('Cache-Control', 'no-store, private');
})->with([User::ROLE_USER, User::ROLE_ADMIN]);

test('guests cannot rotate a license', function (): void {
    $license = License::factory()->create();
    $this->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1'])
        ->assertRedirect(route('login'));
});

test('dashboard cannot rotate another account or unassigned license even for an admin', function (string $role, bool $assigned): void {
    $user = User::factory()->create(['role' => $role]);
    $license = License::factory()->create(['user_id' => $assigned ? User::factory()->create()->id : null, 'encrypted_key' => 'UNCHANGED-KEY']);

    $this->actingAs($user)->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1'])->assertForbidden();
    expect($license->fresh()->encrypted_key)->toBe('UNCHANGED-KEY');
})->with([User::ROLE_USER, User::ROLE_ADMIN])->with([true, false]);

test('rotation requires confirmation and blocks revoked licenses', function (): void {
    $user = User::factory()->create();
    $license = License::factory()->create(['user_id' => $user->id, 'encrypted_key' => 'UNCHANGED-KEY']);
    $endpoint = route('account.profile.licenses.rotate', $license);

    $this->actingAs($user)->from(route('account.profile.dashboard'))->post($endpoint)
        ->assertRedirect(route('account.profile.dashboard'))->assertSessionHasErrors('confirm_rotation');
    expect($license->fresh()->encrypted_key)->toBe('UNCHANGED-KEY');
    $license->update(['status' => 'revoked']);
    $this->post($endpoint, ['confirm_rotation' => '1'])->assertForbidden();
    $this->get(route('account.profile.dashboard'))->assertOk()->assertDontSee($endpoint, false);
    expect($license->fresh()->encrypted_key)->toBe('UNCHANGED-KEY');
});

test('rotation retains suspended and expired states and supports legacy keys', function (string $status): void {
    $user = User::factory()->create();
    $license = License::factory()->create(['user_id' => $user->id, 'status' => $status, 'encrypted_key' => null]);

    $this->actingAs($user)->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1'])
        ->assertRedirect(route('account.profile.dashboard'));
    expect($license->fresh()->status)->toBe($status)->and($license->fresh()->encrypted_key)->not->toBeNull();
})->with(['unused', 'suspended', 'expired']);

test('rotation rechecks ownership after locking the current record', function (): void {
    $owner = User::factory()->create();
    $license = License::factory()->create(['user_id' => $owner->id, 'encrypted_key' => 'UNCHANGED-KEY']);
    $staleLicense = $license->fresh();
    $license->update(['user_id' => User::factory()->create()->id]);
    $request = Request::create('/dashboard', 'POST');
    $request->setUserResolver(fn () => $owner);

    expect(fn () => app(RotateLicenseAction::class)->handle($staleLicense, $request))->toThrow(AuthorizationException::class);
    expect($license->fresh()->encrypted_key)->toBe('UNCHANGED-KEY');
});

test('owner key rotation is rate limited', function (): void {
    $user = User::factory()->create();
    $license = License::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1'])->assertRedirect();
    }
    $key = $license->fresh()->encrypted_key;
    $this->post(route('account.profile.licenses.rotate', $license), ['confirm_rotation' => '1'])->assertTooManyRequests();
    expect($license->fresh()->encrypted_key)->toBe($key);
});
