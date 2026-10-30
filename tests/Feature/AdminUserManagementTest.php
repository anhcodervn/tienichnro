<?php

use App\Jobs\SaveUserLogJob;
use App\Jobs\SendSystemMailJob;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

test('admin can update user information without changing protected fields', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'OldPassword123']);
    Wallet::factory()->create(['user_id' => $user->id, 'balance' => 45000]);
    $this->actingAs($admin)->patchJson('/api/admin-api/users/'.$user->id, [
        'username' => 'updated-user', 'email' => ' NEW@EXAMPLE.COM ', 'full_name' => 'Updated User', 'phone' => '0912345678',
        'avatar' => 'https://example.com/avatar.png', 'role' => 'admin', 'status' => 'banned', 'password' => 'IgnoredPassword123', 'wallet_balance' => 999999,
    ])->assertOk()->assertJsonPath('data.username', 'updated-user')->assertJsonPath('data.full_name', 'Updated User')
        ->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.email_verified_at', null)->assertJsonPath('data.wallet_balance', 45000);
    expect($user->fresh()->role)->toBe('user')->and($user->fresh()->status)->toBe('active')
        ->and(Hash::check('OldPassword123', $user->fresh()->password))->toBeTrue();
    Queue::assertPushed(SaveUserLogJob::class, fn ($job) => $job->userId === $user->id && $job->action === 'admin_profile_updated');
});

test('unchanged email retains verification and missing wallets are displayed without creation', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['username' => 'legacy.user', 'email_verified_at' => now()]);
    $this->actingAs($admin)->getJson('/api/admin-api/users/'.$user->id)->assertOk()->assertJsonPath('data.wallet_balance', 0)->assertJsonMissingPath('data.password');
    $this->assertDatabaseCount('wallets', 0);
    $this->patchJson('/api/admin-api/users/'.$user->id, ['username' => $user->username, 'email' => $user->email, 'full_name' => 'Changed name'])->assertOk();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('admin profile edits validate identity uniqueness and avatar urls', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $other = User::factory()->create(['phone' => '0912345678']);
    $this->actingAs($admin)->patchJson('/api/admin-api/users/'.$user->id, [
        'username' => $other->username, 'email' => $other->email, 'phone' => $other->phone, 'avatar' => 'javascript:alert(1)',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'phone', 'avatar']);
    $this->patchJson('/api/admin-api/users/'.$user->id, ['username' => 'invalid.user', 'email' => $user->email])
        ->assertUnprocessable()->assertJsonValidationErrors('username');
});

test('user management mutations reject guests and regular members', function (string $action): void {
    $user = User::factory()->create();
    $url = '/api/admin-api/'.($action === 'wallet' ? 'wallets/'.$user->id.'/adjust' : 'users/'.$user->id.($action === 'password' ? '/reset-password' : ''));
    $method = $action === 'profile' ? 'patchJson' : 'postJson';
    $this->{$method}($url, [])->assertUnauthorized();
    $this->actingAs($user)->{$method}($url, [])->assertForbidden();
})->with(['profile', 'wallet', 'password']);

test('admin credits and debits selected user with safe retries and auditable history', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $url = '/api/admin-api/wallets/'.$user->id.'/adjust';
    $credit = ['direction' => 'credit', 'amount' => 100000, 'description' => 'Manual credit', 'request_id' => (string) Str::uuid()];
    $debit = ['direction' => 'debit', 'amount' => 30000, 'description' => 'Manual debit', 'request_id' => (string) Str::uuid()];
    $this->actingAs($admin)->postJson($url, $credit)->assertOk()->assertJsonPath('balance', 100000);
    $this->postJson($url, $credit)->assertOk()->assertJsonPath('balance', 100000);
    $this->postJson($url, $debit)->assertOk()->assertJsonPath('balance', 70000);
    $this->postJson($url, [...$debit, 'amount' => 80000, 'request_id' => (string) Str::uuid()])->assertUnprocessable();
    $this->assertDatabaseCount('wallet_transactions', 2);
    $this->getJson('/api/admin-api/wallets/'.$user->id.'/transactions')->assertOk()->assertJsonPath('data.0.actor_id', $admin->id)->assertJsonPath('data.0.balance_after', 70000);
    $this->getJson('/api/admin-api/users/'.$user->id)->assertOk()->assertJsonPath('data.wallet_balance', 70000);
});

test('password reset revokes access and notifies without leaking plaintext', function (): void {
    Queue::fake();
    config(['session.driver' => 'database']);
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['password' => 'OldPassword123', 'remember_token' => 'old-remember-token']);
    $other = User::factory()->create();
    $user->createToken('old-token');
    $other->createToken('other-token');
    foreach ([$user, $other] as $sessionUser) {
        DB::table('sessions')->insert(['id' => 'session-'.$sessionUser->id, 'user_id' => $sessionUser->id, 'payload' => '', 'last_activity' => time()]);
    }
    $resetToken = Password::broker()->createToken($user);
    $response = $this->actingAs($admin)->postJson('/api/admin-api/users/'.$user->id.'/reset-password', [
        'password' => 'NewPassword123', 'password_confirmation' => 'NewPassword123',
    ])->assertOk();
    expect(Hash::check('NewPassword123', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('old-remember-token')
        ->and($user->tokens()->count())->toBe(0)->and($other->tokens()->count())->toBe(1)
        ->and(Password::broker()->tokenExists($user, $resetToken))->toBeFalse();
    $this->assertDatabaseMissing('sessions', ['id' => 'session-'.$user->id]);
    $this->assertDatabaseHas('sessions', ['id' => 'session-'.$other->id]);
    Queue::assertPushed(SaveUserLogJob::class, fn ($job) => $job->userId === $user->id && $job->action === 'admin_reset_password');
    Queue::assertPushed(SendSystemMailJob::class, fn ($job) => $job->to === $user->email && ! str_contains(json_encode($job->messageLines), 'NewPassword123'));
    expect($response->getContent())->not->toContain('NewPassword123');
    $this->assertDatabaseCount('admin_audit_logs', 0);
});

test('invalid reset input leaves the password unchanged', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['password' => 'OldPassword123']);
    $this->actingAs($admin)->postJson('/api/admin-api/users/'.$user->id.'/reset-password', ['password' => 'weak', 'password_confirmation' => 'different'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(Hash::check('OldPassword123', $user->fresh()->password))->toBeTrue();
    Queue::assertNotPushed(SendSystemMailJob::class);
});
