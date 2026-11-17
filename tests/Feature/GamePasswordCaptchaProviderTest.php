<?php

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordCaptchaService;
use App\Features\Client\GamePassword\Services\GamePasswordConfigurationService;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
    $this->configuration = app(GamePasswordConfigurationService::class);
});

test('provider selection isolates encrypted keys and proxy while retaining legacy autocaptcha configuration', function (): void {
    $this->configuration->configure(['price' => 100, 'captcha_api_key' => 'legacy-secret']);
    expect($this->configuration->provider())->toBe('autocaptcha');
    $this->configuration->configure(['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_api_key' => 'point_secret', 'captcha_proxy' => 'http://user:secret@proxy.test:8080']);
    expect($this->configuration->apiKey())->toBe('point_secret')->and($this->configuration->configuration()['has_api_key'])->toBeTrue();
    foreach (['game_password.captcha69.api_key', 'game_password.captcha69.proxy'] as $key) {
        expect(Setting::where('key', $key)->value('type'))->toBe('encrypted');
        expect(Setting::where('key', $key)->value('value'))->not->toContain('secret');
    }
    expect(json_encode($this->configuration->configuration()))->not->toContain('secret', 'proxy.test');
    $this->configuration->configure(['price' => 100, 'captcha_provider' => 'autocaptcha']);
    expect($this->configuration->apiKey())->toBe('legacy-secret');
    $this->configuration->configure(['price' => 100, 'captcha_provider' => 'captcha69']);
    expect($this->configuration->apiKey())->toBe('point_secret');
    $this->configuration->configure(['price' => 100, 'clear_proxy' => true]);
    expect($this->configuration->configuration()['has_api_key'])->toBeFalse()->and($this->configuration->apiKey())->toBe('point_secret');
    $this->configuration->configure(['price' => 100, 'clear_api_key' => true]);
    expect($this->configuration->apiKey())->toBe('')->and($this->configuration->apiKey('autocaptcha'))->toBe('legacy-secret');
});

test('admin validates provider and proxy without exposing stored credentials', function (): void {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
    $url = route('admin.game-password.configure');
    $this->putJson($url, ['price' => 100, 'captcha_provider' => 'unknown'])->assertUnprocessable()->assertJsonValidationErrors('captcha_provider');
    $this->putJson($url, ['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_proxy' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('captcha_proxy');
    $this->putJson($url, ['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_api_key' => 'point_private', 'captcha_proxy' => 'http://name:private@proxy.test:8080'])
        ->assertOk()->assertJsonPath('data.captcha_provider', 'captcha69')->assertJsonPath('data.has_api_key', true)->assertDontSee('private');
});

test('captcha69 polls task results instead of submitting the task id as a token', function (): void {
    $this->configuration->configure(['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_api_key' => 'point_key', 'captcha_proxy' => 'http://name:password@proxy.test:8080']);
    Http::fake([
        'captcha69.com/in.php' => Http::response('OK|task-123'),
        'captcha69.com/res.php' => Http::sequence()->push('CAPCHA_NOT_READY')->push('OK|real-token'),
    ]);
    expect(app(GamePasswordCaptchaService::class)->solve('sitekey', 'https://forum.ngocrongonline.com/app/doi-mat-khau', microtime(true) + 60))->toBe('real-token');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://captcha69.com/in.php' && $request->method() === 'POST'
        && $request['method'] === 'turnstile_point' && $request['pageurl'] === 'https://forum.ngocrongonline.com/app/doi-mat-khau'
        && $request['proxy'] === 'http://name:password@proxy.test:8080' && $request['sitekey'] === 'sitekey');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://captcha69.com/res.php' && $request['id'] === 'task-123' && $request['key'] === 'point_key');
    Http::assertSentCount(3);
});

test('captcha69 rejects missing proxy provider errors and exhausted deadlines without leaking responses', function (): void {
    $this->configuration->configure(['price' => 100, 'captcha_provider' => 'captcha69', 'captcha_api_key' => 'point_key']);
    $solver = app(GamePasswordCaptchaService::class);
    expect(fn () => $solver->solve('site', 'https://example.test', microtime(true) + 60))->toThrow(GamePasswordFailure::class, 'SOLVER_NOT_CONFIGURED');
    Http::assertNothingSent();
    $this->configuration->configure(['price' => 100, 'captcha_proxy' => 'http://user:pass@proxy.test:8080']);
    $createResponse = 'ERROR_private-response';
    $pollResponse = 'ERROR_private-response';
    Http::fake(function (Request $request) use (&$createResponse, &$pollResponse) {
        return Http::response(str_ends_with($request->url(), '/in.php') ? $createResponse : $pollResponse);
    });
    expect(fn () => $solver->solve('site', 'https://example.test', microtime(true) + 60))->toThrow(GamePasswordFailure::class, 'SOLVER_FAILED');
    $createResponse = 'OK|task';
    expect(fn () => $solver->solve('site', 'https://example.test', microtime(true) + 60))->toThrow(GamePasswordFailure::class, 'SOLVER_FAILED');
    expect(fn () => $solver->solve('site', 'https://example.test', microtime(true) - 1))->toThrow(GamePasswordFailure::class, 'REMOTE_TIMEOUT');
    $pollResponse = 'CAPCHA_NOT_READY';
    expect(fn () => $solver->solve('site', 'https://example.test', microtime(true) + 300))->toThrow(GamePasswordFailure::class, 'REMOTE_TIMEOUT');
});
