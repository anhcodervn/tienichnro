<?php

use App\Jobs\SaveUserLogJob;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserLog;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

test('profile tabs require authentication', function (string $routeName): void {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'overview' => 'account.index',
    'profile' => 'account.profile.edit',
    'password' => 'account.profile.password',
    'api key' => 'account.profile.api',
    'user logs' => 'account.profile.logs',
    'wallet history' => 'account.profile.wallet',
]);

test('authenticated user can navigate every profile tab', function (): void {
    $user = User::factory()->create();

    $expectedPages = [
        'account.index' => 'Hồ sơ hiển thị',
        'account.profile.edit' => 'Hồ sơ hiển thị',
        'account.profile.password' => 'Đổi mật khẩu',
        'account.profile.api' => 'Quản lý API key',
        'account.profile.logs' => 'Lịch sử người dùng',
        'account.profile.wallet' => 'Lịch sử dòng tiền',
    ];

    foreach ($expectedPages as $routeName => $heading) {
        $this->actingAs($user)
            ->get(route($routeName))
            ->assertSuccessful()
            ->assertSee($heading)
            ->assertSee('Thông tin user')
            ->assertSee('Đổi mật khẩu')
            ->assertSee('API key')
            ->assertSee('Lịch sử người dùng')
            ->assertSee('Lịch sử dòng tiền');
    }
});

test('user can update profile fields but not account identity', function (): void {
    $user = User::factory()->create([
        'username' => 'immutable-user',
        'email' => 'immutable@example.com',
    ]);
    Queue::fake();

    $this->actingAs($user)
        ->patch(route('account.profile.update'), [
            'avatar' => 'https://example.com/avatar.jpg',
            'full_name' => 'Nguyễn Văn Mới',
            'phone' => '0901234567',
            'username' => 'forged-user',
            'email' => 'forged@example.com',
        ])
        ->assertRedirect(route('account.profile.edit'))
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->avatar)->toBe('https://example.com/avatar.jpg')
        ->and($user->full_name)->toBe('Nguyễn Văn Mới')
        ->and($user->phone)->toBe('0901234567')
        ->and($user->username)->toBe('immutable-user')
        ->and($user->email)->toBe('immutable@example.com');

    Queue::assertPushed(SaveUserLogJob::class, fn (SaveUserLogJob $job): bool => $job->action === 'profile_updated');
});

test('password change validates the current password and queues an audit log', function (): void {
    $user = User::factory()->create(['password' => 'old-password']);
    Queue::fake();

    $this->actingAs($user)
        ->from(route('account.profile.password'))
        ->put(route('account.profile.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect(route('account.profile.password'))
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->put(route('account.profile.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])
        ->assertRedirect(route('account.profile.password'))
        ->assertSessionHas('success');

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
    Queue::assertPushed(SaveUserLogJob::class, fn (SaveUserLogJob $job): bool => $job->action === 'password_changed');
});

test('api key and secret are shown once while only the secret hash is stored', function (): void {
    $user = User::factory()->create();
    Queue::fake();
    $plainCredentials = null;

    $response = $this->actingAs($user)
        ->post(route('account.profile.api.store'), ['name' => 'Desktop integration'])
        ->assertRedirect(route('account.profile.api'))
        ->assertSessionHas('success');

    $response->assertSessionHas('new_api_credentials', function (array $credentials) use (&$plainCredentials): bool {
        $plainCredentials = $credentials;

        return str_starts_with($credentials['api_key'], 'nck_')
            && str_starts_with($credentials['api_secret'], 'ncs_');
    });

    $storedKey = ApiKey::query()->sole();

    expect($storedKey->name)->toBe('Desktop integration')
        ->and($storedKey->api_key)->toBe($plainCredentials['api_key'])
        ->and(Hash::check($plainCredentials['api_secret'], $storedKey->api_secret_hash))->toBeTrue()
        ->and($storedKey->getRawOriginal('api_secret_hash'))->not->toContain($plainCredentials['api_secret'])
        ->and($storedKey->api_secret_encrypted)->toBeNull()
        ->and($storedKey->permissions)->toBe([
            'balance:read',
            'tasks:create',
            'tasks:read',
        ]);

    $this->actingAs($user)
        ->get(route('account.profile.api'))
        ->assertSuccessful()
        ->assertSee($plainCredentials['api_key'])
        ->assertSee($plainCredentials['api_secret'])
        ->assertSee('X-API-KEY')
        ->assertSee('X-API-SECRET');

    Queue::assertPushed(SaveUserLogJob::class, fn (SaveUserLogJob $job): bool => $job->action === 'api_key_created');
});

test('user can revoke only their own api key', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownedKey = ApiKey::factory()->for($user)->create(['name' => 'Owned key']);
    $foreignKey = ApiKey::factory()->for($otherUser)->create(['name' => 'Foreign key']);
    Queue::fake();

    $this->actingAs($user)
        ->delete(route('account.profile.api.destroy', $ownedKey->id))
        ->assertRedirect(route('account.profile.api'));

    expect($ownedKey->refresh()->status)->toBe('revoked');

    $this->actingAs($user)
        ->delete(route('account.profile.api.destroy', $foreignKey->id))
        ->assertNotFound();

    expect($foreignKey->refresh()->status)->toBe('active');
    Queue::assertPushed(SaveUserLogJob::class, fn (SaveUserLogJob $job): bool => $job->action === 'api_key_revoked');
});

test('activity and wallet tabs show only records owned by the signed in user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    UserLog::query()->create([
        'user_id' => $user->id,
        'action' => 'login',
        'description' => 'OWN ACTIVITY',
        'ip' => '127.0.0.1',
    ]);
    UserLog::query()->create([
        'user_id' => $otherUser->id,
        'action' => 'login',
        'description' => 'FOREIGN ACTIVITY',
        'ip' => '10.0.0.1',
    ]);
    WalletTransaction::query()->create([
        'wallet_id' => $user->wallet()->firstOrFail()->id,
        'type' => 'credit',
        'amount' => 100000,
        'balance_before' => 0,
        'balance_after' => 100000,
        'description' => 'OWN WALLET ENTRY',
        'status' => 'success',
    ]);
    WalletTransaction::query()->create([
        'wallet_id' => $otherUser->wallet()->firstOrFail()->id,
        'type' => 'credit',
        'amount' => 200000,
        'balance_before' => 0,
        'balance_after' => 200000,
        'description' => 'FOREIGN WALLET ENTRY',
        'status' => 'success',
    ]);

    $this->actingAs($user)
        ->get(route('account.profile.logs'))
        ->assertSuccessful()
        ->assertSee('OWN ACTIVITY')
        ->assertDontSee('FOREIGN ACTIVITY');

    $this->actingAs($user)
        ->get(route('account.profile.wallet'))
        ->assertSuccessful()
        ->assertSee('OWN WALLET ENTRY')
        ->assertDontSee('FOREIGN WALLET ENTRY');
});
