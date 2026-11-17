<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

function passwordFormHtml(): string
{
    return '<html><input name="ts" value="timestamp"><input name="keyLog" value="form-key"><div class="cf-turnstile" data-sitekey="public-site-key"></div></html>';
}

test('retry checks the requested new password before deciding whether another change is necessary', function (array $forumResults, array $expectedPasswords, int $expectedChanges, bool $uncertain): void {
    $passwords = [];
    $changes = 0;
    $forumIndex = 0;
    Http::fake(function (Request $request) use (&$passwords, &$changes, &$forumIndex, $forumResults) {
        if (str_contains($request->url(), 'giaicaptcha.vn')) {
            return Http::response(['status' => true, 'data' => ['task_code' => 'task', 'captcha' => 'token']]);
        }
        if (str_ends_with($request->url(), '/app/dien-dan-0')) {
            return Http::response(($forumResults[$forumIndex++] ?? false) ? '<a href="/app/dang-xuat">Logout</a>' : 'Not logged in');
        }
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/app/dang-nhap')) {
            $passwords[] = $request['pass'];

            return Http::response('Login submitted');
        }
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/app/doi-mat-khau')) {
            $changes++;

            return Http::response('Change submitted');
        }

        return Http::response(passwordFormHtml());
    });
    $failure = null;
    try {
        app(GamePasswordGatewayService::class)->change('account', 'old-secret', 'new-secret', 3, fn (string $phase) => null, true, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
    } catch (GamePasswordFailure $exception) {
        $failure = $exception;
    }
    expect($passwords)->toBe($expectedPasswords)->and($changes)->toBe($expectedChanges);
    if ($uncertain) {
        expect($failure)->toBeInstanceOf(GamePasswordFailure::class)->and($failure->uncertain)->toBeTrue();
    } else {
        expect($failure)->toBeNull();
    }
})->with([
    'new password already works' => [[true], ['new-secret'], 0, false],
    'old password works and needs a change' => [[false, true, true], ['new-secret', 'old-secret', 'new-secret'], 1, false],
    'neither password verified' => [[false, false], ['new-secret', 'old-secret'], 0, true],
]);

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
    Storage::fake('local');
    app(GamePasswordConfigurationService::class)->configure(['price' => 100, 'captcha_api_key' => 'solver-private-key']);
});

test('source flow uses memory cookies fresh verification login and cleans all cookie jars between accounts', function (): void {
    $jars = [];
    $loginPasswords = [];
    $progress = [];
    Http::fake(function (Request $request, array $options) use (&$jars, &$loginPasswords) {
        expect($options['timeout'])->toBeLessThanOrEqual(45)->and($options['verify'] ?? true)->not->toBeFalse();
        if (str_contains($request->url(), 'giaicaptcha.vn')) {
            expect($request->header('X-API-KEY'))->toBe(['user-api-key'])->and($request->header('X-API-SECRET'))->toBe(['user-api-secret']);

            return Http::response(['status' => true, 'data' => ['task_code' => 'task', 'captcha' => 'solved-token']]);
        }
        $jar = $options['cookies'];
        expect($jar)->toBeInstanceOf(CookieJar::class)->and(get_class($jar))->toBe(CookieJar::class);
        $jars[spl_object_id($jar)] = $jar;
        if (str_ends_with($request->url(), '/dang-nhap')) {
            if ($request->method() === 'GET') {
                expect($jar->count())->toBe(0);

                return Http::response(passwordFormHtml());
            }
            $loginPasswords[] = $request['pass'];
            expect((int) $request['server'])->toBe(3)->and($request['cf-turnstile-response'])->toBe('solved-token');
            $jar->setCookie(new SetCookie(['Name' => 'auth', 'Value' => 'private-cookie', 'Domain' => 'forum.ngocrongonline.com', 'Path' => '/']));

            return Http::response('ok');
        }
        if (str_ends_with($request->url(), '/dien-dan-0')) {
            expect($jar->count())->toBe(1);

            return Http::response('<a href="/app/dang-xuat">Logout</a>');
        }
        if ($request->method() === 'GET') {
            return Http::response(passwordFormHtml());
        }
        expect($request['oldPass'])->toBe('old-secret')->and($request['newPass'])->toBe('new-secret')->and($request['cfnewPass'])->toBe('new-secret');

        return Http::response('password changed');
    });
    $gateway = app(GamePasswordGatewayService::class);
    for ($index = 0; $index < 2; $index++) {
        $gateway->change('game-user', 'old-secret', 'new-secret', 3, function (string $step) use (&$progress): void {
            $progress[] = $step;
        }, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        foreach ($jars as $jar) {
            expect($jar->count())->toBe(0);
        }
    }
    expect($jars)->toHaveCount(4)->and($loginPasswords)->toBe(['old-secret', 'new-secret', 'old-secret', 'new-secret'])
        ->and($progress)->toBe(array_merge(...array_fill(0, 2, ['home', 'old_check', 'login_page', 'login_captcha', 'login', 'login_success', 'change', 'change_captcha', 'submitting', 'verify', 'verify_success'])))
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

test('login failure cannot trigger a password change', function (): void {
    Http::fake(fn (Request $request) => match (true) {
        str_contains($request->url(), 'giaicaptcha.vn') => Http::response(['status' => true, 'data' => ['task_code' => 'task', 'captcha' => 'token']]),
        str_ends_with($request->url(), '/dang-nhap') && $request->method() === 'GET' => Http::response(passwordFormHtml()),
        default => Http::response('not logged in'),
    });
    try {
        app(GamePasswordGatewayService::class)->change('user', 'old', 'new-password', 3, fn () => null, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        test()->fail('Expected failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->reason)->toBe('LOGIN_FAILED')->and($failure->uncertain)->toBeFalse();
    }
    Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/doi-mat-khau'));
});

test('changed forms and solver errors fail safely before password submission', function (bool $badForm): void {
    Http::fake(fn (Request $request) => str_contains($request->url(), 'giaicaptcha.vn') ? Http::response(['success' => false, 'message' => 'private upstream detail']) : Http::response($badForm ? '<html>changed</html>' : passwordFormHtml()));
    try {
        app(GamePasswordGatewayService::class)->change('user', 'old', 'new-password', 3, fn () => null, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        test()->fail('Expected failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->reason)->toBe($badForm ? 'FORM_CHANGED' : 'SOLVER_FAILED')->and($failure->uncertain)->toBeFalse()->and($failure->getMessage())->not->toContain('private upstream detail');
    }
})->with([true, false]);

test('network interruptions after submission are uncertain and never replay the password POST', function (bool $duringVerify): void {
    $changes = 0;
    $forumReads = 0;
    Http::fake(function (Request $request) use (&$changes, &$forumReads, $duringVerify) {
        if (str_contains($request->url(), 'giaicaptcha.vn')) {
            return Http::response(['status' => true, 'data' => ['task_code' => 'task', 'captcha' => 'token']]);
        }
        if (str_ends_with($request->url(), '/doi-mat-khau') && $request->method() === 'POST') {
            $changes++;
            if (! $duringVerify) {
                throw new ConnectionException('private-password-and-token');
            }

            return Http::response('ok');
        }
        if (str_ends_with($request->url(), '/dien-dan-0')) {
            if (++$forumReads === 2) {
                throw new ConnectionException('private-password-and-token');
            }

            return Http::response('<a href="/app/dang-xuat">Logout</a>');
        }

        return Http::response(passwordFormHtml());
    });
    try {
        app(GamePasswordGatewayService::class)->change('user', 'old', 'new-password', 3, fn () => null, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        test()->fail('Expected failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->uncertain)->toBeTrue()->and($failure->getMessage())->not->toContain('private-password-and-token');
    }
    expect($changes)->toBe(1);
})->with([true, false]);

test('redirects cannot forward credentials or cookies to a different host', function (): void {
    Http::fake(function (Request $request, array $options) {
        $options['allow_redirects']['on_redirect'](new GuzzleHttp\Psr7\Request('GET', $request->url()), new Response(302), new Uri('https://outside.invalid/login'));

        return Http::response('unreachable');
    });
    try {
        app(GamePasswordGatewayService::class)->change('user', 'old', 'new-password', 3, fn () => null, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        test()->fail('Expected failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->reason)->toBe('REDIRECT_BLOCKED')->and($failure->uncertain)->toBeFalse();
    }
});

test('a successful HTTP response cannot be mistaken for a verified password change', function (): void {
    $forumReads = 0;
    Http::fake(function (Request $request) use (&$forumReads) {
        if (str_contains($request->url(), 'giaicaptcha.vn')) {
            return Http::response(['status' => true, 'data' => ['task_code' => 'task', 'captcha' => 'token']]);
        }
        if (str_ends_with($request->url(), '/dien-dan-0')) {
            return Http::response(++$forumReads === 1 ? '<a href="/app/dang-xuat">Logout</a>' : '<p>Login failed</p>');
        }

        return Http::response(passwordFormHtml());
    });
    try {
        app(GamePasswordGatewayService::class)->change('user', 'old', 'new-password', 3, fn () => null, false, ['key' => 'user-api-key', 'secret' => 'user-api-secret']);
        test()->fail('Expected verification failure');
    } catch (GamePasswordFailure $failure) {
        expect($failure->reason)->toBe('VERIFY_FAILED')->and($failure->uncertain)->toBeTrue();
    }
});
