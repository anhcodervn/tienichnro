<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Notification::fake();
    Queue::fake();
});

test('login and logout display success notifications once on the redirected Blade page', function (): void {
    $user = User::factory()->create(['password' => 'test-password']);
    $this->post(route('auth.login.submit'), ['login' => $user->email, 'password' => 'test-password'])
        ->assertRedirect(route('home'))->assertSessionHas('success', 'Đăng nhập thành công.');
    $this->get(route('home'))->assertOk()->assertSee('data-alert-type="success"', false)->assertSee('Đăng nhập thành công.');
    $this->get(route('home'))->assertDontSee('Đăng nhập thành công.');
    $this->post(route('logout'))->assertRedirect(route('auth.login'))->assertSessionHas('success', 'Đăng xuất thành công.');
    $this->get(route('auth.login'))->assertOk()->assertSee('Đăng xuất thành công.');
    $this->get(route('auth.login'))->assertDontSee('Đăng xuất thành công.');
});

test('invalid login and registration return readable alerts without flashing passwords', function (): void {
    $user = User::factory()->create(['password' => 'correct-password']);
    $this->from(route('auth.login'))->post(route('auth.login.submit'), ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect(route('auth.login'))->assertSessionHasErrors('login')->assertSessionMissing('_old_input.password');
    $this->get(route('auth.login'))->assertSee('data-alert-type="error"', false)->assertSee('Thông tin đăng nhập không chính xác.');
    $this->from(route('auth.register'))->post(route('auth.register.submit'), ['username' => $user->username, 'email' => $user->email])
        ->assertRedirect(route('auth.register'))->assertSessionHasErrors(['username', 'email', 'password']);
    $this->get(route('auth.register'))->assertOk()->assertSee('data-alert-message', false);
});

test('successful registration shows the verification notice on the login page', function (): void {
    $this->post(route('auth.register.submit'), [
        'username' => 'notification-player', 'email' => 'notification-player@example.test',
        'password' => 'test-password', 'password_confirmation' => 'test-password', 'accept_terms' => '1',
    ])->assertRedirect(route('auth.login'))->assertSessionHas('success');
    $this->get(route('auth.login'))->assertOk()->assertSee('data-alert-type="success"', false)
        ->assertSee('Đăng ký thành công. Vui lòng kiểm tra email để xác minh tài khoản.');
});

test('Blade login rate limits redirect to a notification while JSON retains its error response', function (): void {
    $key = Str::transliterate('blocked@example.test|127.0.0.1');
    RateLimiter::hit($key);
    RateLimiter::hit($key);
    RateLimiter::hit($key);
    RateLimiter::hit($key);
    RateLimiter::hit($key);
    $payload = ['login' => 'blocked@example.test', 'password' => 'secret-password'];
    try {
        $this->from(route('auth.login'))->post(route('auth.login.submit'), $payload)
            ->assertRedirect(route('auth.login'))->assertSessionHasErrors('login');
        $this->get(route('auth.login'))->assertOk()->assertSee('data-alert-type="error"', false);
        $this->postJson(route('auth.login.submit'), $payload)->assertTooManyRequests()->assertJsonPath('status', false);
    } finally {
        RateLimiter::clear($key);
    }
});

test('profile and password changes show their existing success notifications', function (): void {
    $user = User::factory()->create(['password' => 'old-password']);
    $this->actingAs($user)->patch(route('account.profile.update'), ['full_name' => 'Updated player'])
        ->assertRedirect(route('account.profile.edit'))->assertSessionHas('success');
    $this->get(route('account.profile.edit'))->assertSee('Thông tin tài khoản đã được cập nhật.');
    $this->put(route('account.profile.password.update'), ['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
        ->assertRedirect(route('account.profile.password'))->assertSessionHas('success');
    $this->get(route('account.profile.password'))->assertSee('Mật khẩu đã được thay đổi.');
});

test('status and Google failures are escaped and rendered for SweetAlert', function (): void {
    $message = '<img src=x onerror=alert(1)> unsafe';
    $this->withSession(['status' => 'Reset link sent'])->get(route('password.request'))->assertSee('Reset link sent')->assertSee('data-alert-type="success"', false);
    $this->withSession(['auth_google_error' => $message])->get(route('auth.login'))
        ->assertSee('Không thể đăng nhập Google')->assertSee($message)->assertDontSee($message, false);
});
