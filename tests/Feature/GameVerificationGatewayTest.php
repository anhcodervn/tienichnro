<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GameVerification\Services\GameVerificationGatewayService;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
    Storage::fake('local');
    $this->loggedIn = true;
    $this->profile = '<div class="text-2xl font-mono"><span>A1b2C3d4</span></div>';
    $this->jars = [];
    Http::fake(function (Request $request, array $options) {
        if (str_contains($request->url(), 'giaicaptcha.vn')) {
            expect($request->header('X-API-KEY'))->toBe(['ak_user'])->and($request->header('X-API-SECRET'))->toBe(['sk_user']);

            return Http::response(['status' => true, 'data' => ['task_code' => 'ct_test', 'captcha' => 'solved-token']]);
        }
        $this->jars[] = $options['cookies'];
        expect(get_class($options['cookies']))->toBe(CookieJar::class);
        if (str_ends_with($request->url(), '/dang-nhap')) {
            if ($request->method() === 'POST') {
                expect($request['user'])->toBe('my-account')->and($request['pass'])->toBe('private-password')->and((int) $request['server'])->toBe(3);
            }

            return Http::response('<input name="ts" value="time"><input name="keyLog" value="form-key"><div data-sitekey="site-key"></div>');
        }
        if (str_ends_with($request->url(), '/dien-dan-0')) {
            return Http::response($this->loggedIn ? '<a href="/app/dang-xuat">Logout</a>' : 'Login failed');
        }

        return Http::response($this->profile);
    });
});

test('verification logs in with personal captcha and extracts both source markup variants without files', function (string $html): void {
    $this->profile = $html;
    $progress = [];
    $syntax = app(GameVerificationGatewayService::class)->verify('my-account', 'private-password', 3, function (string $code) use (&$progress): void {
        $progress[] = $code;
    }, ['key' => 'ak_user', 'secret' => 'sk_user']);
    expect($syntax)->toBe('GO NRO-XT my-account A1b2C3d4')->and($progress)->toBe(['login_page', 'login_captcha', 'login', 'login_success', 'profile', 'extract']);
    foreach ($this->jars as $jar) {
        expect($jar->count())->toBe(0);
    }
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['<div class="text-2xl font-mono"><span>A1b2C3d4</span></div>', '<h2 class="font-mono">GO NRO-XT my-account A1b2C3d4</h2>']);

test('missing syntax and different account responses fail without exposing upstream content', function (string $html): void {
    $this->profile = $html;
    expect(fn () => app(GameVerificationGatewayService::class)->verify('my-account', 'private-password', 3, fn () => null, ['key' => 'ak_user', 'secret' => 'sk_user']))->toThrow(GamePasswordFailure::class, 'SYNTAX_MISSING');
})->with(['<h2 class="font-mono">GO NRO-XT different-account A1b2C3d4</h2>', '<html>private-password and cookies</html>']);

test('failed login cannot reach the personal page and missing captcha never uses a website key', function (): void {
    $this->loggedIn = false;
    expect(fn () => app(GameVerificationGatewayService::class)->verify('my-account', 'private-password', 3, fn () => null, ['key' => 'ak_user', 'secret' => 'sk_user']))->toThrow(GamePasswordFailure::class, 'LOGIN_FAILED');
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/thong-tin-ca-nhan'));
    expect(fn () => app(GameVerificationGatewayService::class)->verify('my-account', 'private-password', 3, fn () => null, []))->toThrow(GamePasswordFailure::class, 'USER_SOLVER_NOT_CONFIGURED');
});
