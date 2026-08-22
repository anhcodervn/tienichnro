<?php

use App\Features\Reporting\Jobs\SendDiscordReport;
use App\Models\TopupProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['services.internal_cron.key' => 'private-cron-key']);
    Http::preventStrayRequests();
    Cache::flush();
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
        ->assertJsonPath('data.warning_threshold', 1000000)
        ->assertJsonPath('data.is_below_warning_threshold', false)
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

test('the9p balance cron queues a low balance alert using the provider threshold', function (): void {
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
            'balance_warning_threshold' => 2000000,
        ],
    ]);
    config(['services.discord.channels.alerts' => 'https://discord.test/alerts']);
    Queue::fake([SendDiscordReport::class]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::response([
            'status' => 'success',
            'data' => ['balance' => 900000, 'currency' => 'VND'],
        ]),
    ]);

    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->getJson(route('api.cron.the9p.balance'))
        ->assertSuccessful()
        ->assertJsonPath('data.warning_threshold', 2000000)
        ->assertJsonPath('data.is_below_warning_threshold', true);
    $this->withHeader('X-Cron-Key', 'private-cron-key')
        ->getJson(route('api.cron.the9p.balance'))
        ->assertSuccessful();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'alerts'
        && $job->title === 'Cảnh báo số dư cổng nạp thấp'
        && $job->details['Số dư hiện tại'] === '900.000đ'
        && $job->details['Ngưỡng cảnh báo'] === '2.000.000đ'
        && $job->details['Số tiền cần bổ sung'] === '1.100.000đ'
        && str_contains($job->dedupeKey, "provider-balance:{$provider->id}:low:"));
    Queue::assertPushed(SendDiscordReport::class, 1);
});

test('the9p balance cron reports recovery after a previous low balance warning', function (): void {
    $provider = TopupProvider::factory()->create([
        'slug' => 'the9p',
        'connection_config' => [
            'partner_id' => 'partner-123',
            'partner_key' => 'secret-key',
        ],
    ]);
    config([
        'services.discord.channels.alerts' => 'https://discord.test/alerts',
        'services.discord.channels.recovered' => 'https://discord.test/recovered',
    ]);
    Queue::fake([SendDiscordReport::class]);
    Http::fake([
        'https://the9p.com/api/rechargews' => Http::sequence()
            ->push(['status' => 'success', 'data' => ['balance' => 999999, 'currency' => 'VND']])
            ->push(['status' => 'success', 'data' => ['balance' => 1000000, 'currency' => 'VND']]),
    ]);

    $this->withHeader('X-Cron-Key', 'private-cron-key')->getJson(route('api.cron.the9p.balance'))->assertSuccessful();
    $this->withHeader('X-Cron-Key', 'private-cron-key')->getJson(route('api.cron.the9p.balance'))->assertSuccessful();

    Queue::assertPushed(SendDiscordReport::class, fn (SendDiscordReport $job): bool => $job->channel === 'recovered'
        && $job->title === 'Số dư cổng nạp đã phục hồi'
        && $job->details['Số dư hiện tại'] === '1.000.000đ'
        && str_contains($job->dedupeKey, "provider-balance:{$provider->id}:recovered:"));
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
