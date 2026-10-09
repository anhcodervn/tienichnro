<?php

use App\Jobs\SendSystemMailJob;
use App\Models\User;
use App\Notifications\QueuedResetPasswordNotification;
use App\Notifications\QueuedVerifyEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Auth;
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
    $this->get(route('auth.login'))->assertOk()->assertSee('Đăng nhập')->assertSee('data-client-auth-form', false);
    $this->get(route('auth.register'))->assertOk()->assertSee('Tạo tài khoản')->assertSee('data-client-auth-form', false);
    $this->get(route('password.request'))->assertOk()->assertSee('Quên mật khẩu');
});

test('ajax login authenticates with a session and returns the intended destination', function (bool $hasIntended): void {
    $user = User::factory()->create();
    $destination = $hasIntended ? route('account.index') : route('home');

    $this->withSession($hasIntended ? ['url.intended' => $destination] : [])
        ->postJson(route('auth.login.submit'), [
            'login' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('redirect', $destination)
        ->assertSessionMissing('url.intended')
        ->assertCookie(Auth::guard('web')->getRecallerName());

    $this->assertAuthenticatedAs($user);
    expect($user->tokens()->count())->toBe(0);
})->with([true, false]);

test('ajax authentication failures return field errors without redirecting', function (): void {
    $user = User::factory()->create();

    $this->postJson(route('auth.login.submit'), [
        'login' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('login');

    $this->postJson(route('auth.register.submit'), [
        'username' => $user->username,
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ])->assertUnprocessable()->assertJsonValidationErrors(['username', 'email', 'password', 'accept_terms']);

    $this->assertGuest();
    expect(User::query()->count())->toBe(1);
});

test('ajax registration returns the login destination and queues verification', function (): void {
    $this->postJson(route('auth.register.submit'), [
        'username' => 'ajaxplayer',
        'email' => 'AJAX@EXAMPLE.COM',
        'password' => 'password',
        'password_confirmation' => 'password',
        'accept_terms' => '1',
    ])->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('redirect', route('auth.login'));

    $user = User::query()->where('email', 'ajax@example.com')->firstOrFail();
    Notification::assertSentTo($user, QueuedVerifyEmailNotification::class);
    Queue::assertPushed(SendSystemMailJob::class);
    $this->assertGuest();
});

test('authenticated client header shows the account without retired commerce or support chat', function (): void {
    $user = User::factory()->create([
        'avatar' => 'https://example.com/avatar.png',
        'email' => 'player@example.com',
    ]);
    $this->actingAs($user)->get(route('account.index'))
        ->assertOk()
        ->assertSee('player@example.com')
        ->assertSee('https://example.com/avatar.png')
        ->assertSee(route('account.index'), false)
        ->assertSee(route('logout'), false)
        ->assertSee('data-mobile-bottom-nav', false)
        ->assertDontSee('data-header-wallet-balance', false)
        ->assertDontSee('data-mobile-header-wallet-balance', false)
        ->assertDontSee('/tai-khoan/ho-tro', false);
});

test('guest client header offers login and registration without an account logout', function (): void {
    $this->get(route('home'))->assertOk()
        ->assertSee(route('auth.login'), false)
        ->assertSee(route('auth.register'), false)
        ->assertSee('data-mobile-bottom-nav', false)
        ->assertDontSee(route('logout'), false);
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
    ])->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'))->assertRedirect(route('auth.login'));
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
