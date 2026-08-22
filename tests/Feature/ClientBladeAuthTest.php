<?php

use App\Jobs\SendSystemMailJob;
use App\Models\User;
use App\Notifications\QueuedResetPasswordNotification;
use App\Notifications\QueuedVerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function (): void {
    Http::fake();
    Notification::fake();
    Queue::fake();
});

test('client authentication pages are rendered by blade', function (): void {
    $this->get(route('auth.login'))->assertOk()->assertSee('Đăng nhập');
    $this->get(route('auth.register'))->assertOk()->assertSee('Tạo tài khoản');
    $this->get(route('password.request'))->assertOk()->assertSee('Quên mật khẩu');
});

test('authenticated client header shows the account dropdown and current wallet balance', function (): void {
    $user = User::factory()->create([
        'avatar' => 'https://example.com/avatar.png',
        'email' => 'player@example.com',
    ]);
    $user->wallet()->firstOrFail()->update(['balance' => 101925579]);

    $this->actingAs($user)
        ->get(route('account.index'))
        ->assertOk()
        ->assertSee('data-account-menu', false)
        ->assertSee('data-account-menu-toggle', false)
        ->assertSee('data-account-menu-panel', false)
        ->assertSee('data-header-wallet-balance', false)
        ->assertSee('player@example.com')
        ->assertSee('101.925.579đ')
        ->assertSee('https://example.com/avatar.png')
        ->assertSee(route('account.orders.index'), false)
        ->assertSee(route('wallet.deposit.index'), false);
});

test('guest client header does not render an account dropdown', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('data-account-menu', false);
});

test('user can register login and logout through clean client routes', function (): void {
    $this->post(route('auth.register.submit'), [
        'username' => 'ninjaplayer',
        'name' => 'Ninja Player',
        'email' => 'PLAYER@EXAMPLE.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => '1',
    ])->assertRedirect(route('auth.login'));

    $user = User::query()->where('email', 'player@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, QueuedVerifyEmailNotification::class, function (QueuedVerifyEmailNotification $notification): bool {
        return $notification instanceof ShouldQueue
            && $notification->queue === 'mails'
            && $notification->afterCommit === true;
    });
    Queue::assertPushed(SendSystemMailJob::class, function (SendSystemMailJob $job): bool {
        return $job->queue === 'mails'
            && $job->afterCommit === true
            && $job->subjectText === 'Đăng ký tài khoản thành công';
    });

    $this->post(route('auth.login.submit'), [
        'login' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.index'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('password reset email notification is queued after commit', function (): void {
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('reset-token');

    Notification::assertSentTo($user, QueuedResetPasswordNotification::class, function (QueuedResetPasswordNotification $notification) use ($user): bool {
        return $notification instanceof ShouldQueue
            && $notification->queue === 'mails'
            && $notification->afterCommit === true
            && $notification->toMail($user)->subject === 'Đặt lại mật khẩu Nạp Carot';
    });
});

test('signed verification route verifies the email', function (): void {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('account.index'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});
