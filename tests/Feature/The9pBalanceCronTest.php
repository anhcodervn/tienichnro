<?php

use App\Models\TopupProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['services.internal_cron.key' => 'private-cron-key']);
    Http::preventStrayRequests();
});

test('the9p balance cron route rejects requests without the internal key', function (): void {
    $this->getJson(route('api.cron.the9p.balance'))->assertForbidden();
    $this->withHeader('X-Cron-Key', 'wrong-key')
        ->getJson(route('api.cron.the9p.balance'))
        ->assertForbidden();
    $this->getJson(route('api.cron.the9p.balance', ['key' => 'private-cron-key']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('the9p balance cron route refuses to send credentials over an insecure endpoint', function (): void {
    TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'http://the9p.test/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    Http::fake();

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->getJson(route('api.cron.the9p.balance'))
        ->assertStatus(502);

    Http::assertNothingSent();
});

test('the9p balance cron route signs the request according to the recharge documentation', function (): void {
    TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'base_url' => 'https://the9p.com/api/rechargews',
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
            'connect_timeout' => 5,
            'timeout' => 20,
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'message' => 'Thành công',
            'data' => ['balance' => 1200000, 'currency' => 'VND'],
        ]),
    ]);

    $response = $this->withToken('private-cron-key')
        ->getJson(route('api.cron.the9p.balance'));

    $response->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.balance', 1200000)
        ->assertJsonPath('data.currency', 'VND')
        ->assertJsonMissingPath('data.provider')
        ->assertJsonMissingPath('data.partner_id');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://the9p.com/api/rechargews'
            && $request->method() === 'POST'
            && $payload === [
                'command' => 'getbalance',
                'partner_id' => 'partner-123',
                'sign' => md5('secret-keypartner-123getbalance'),
            ];
    });
});

test('the9p balance cron route returns a safe gateway error for an invalid provider response', function (): void {
    TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'error',
            'message' => 'Credential or provider details must not leak',
        ]),
    ]);

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->getJson(route('api.cron.the9p.balance'))
        ->assertStatus(502)
        ->assertJsonPath('status', false)
        ->assertJsonMissing(['Credential or provider details must not leak', 'partner-123', 'secret-key']);
});
