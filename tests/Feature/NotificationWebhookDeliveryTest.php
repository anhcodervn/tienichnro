<?php

use App\Features\NroNotification\Jobs\DeliverNotificationWebhook;
use App\Features\NroNotification\Services\NotificationWebhookService;
use App\Features\NroNotification\Services\NroNotificationService;
use App\Models\NotificationSubscription;
use App\Models\NotificationWebhookDelivery;
use App\Rules\PublicWebhookUrl;
use Database\Seeders\NroNotificationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
});
function runNotificationWebhook(NotificationWebhookDelivery $delivery): void
{
    (new DeliverNotificationWebhook($delivery->id))->handle(app(PublicWebhookUrl::class), app(NotificationWebhookService::class));
}
test('outbox reserves once deduplicates and skips expired inactive and exhausted subscriptions', function (): void {
    $subscription = NotificationSubscription::factory()->create(['billing_type' => 'usage', 'remaining_uses' => 1]);
    NotificationSubscription::factory()->create(['expires_at' => now()->subSecond()]);
    NotificationSubscription::factory()->create(['status' => 'pending']);
    NotificationSubscription::factory()->create(['billing_type' => 'usage', 'remaining_uses' => 0]);
    $service = app(NotificationWebhookService::class);
    $service->capture(str_repeat('a', 64), ['content' => 'First']);
    $service->capture(str_repeat('a', 64), ['content' => 'First']);
    $service->capture(str_repeat('b', 64), ['content' => 'Second']);
    $this->assertDatabaseCount('notification_webhook_deliveries', 1);
    expect($subscription->fresh()->remaining_uses)->toBe(0);
    expect(NotificationWebhookDelivery::query()->first()->quota_reserved)->toBeTrue();
});
test('delivery signs raw body sends only once and permits last reserved usage', function (): void {
    Http::fake(['*' => Http::response('', 204)]);
    $subscription = NotificationSubscription::factory()->create(['billing_type' => 'usage', 'remaining_uses' => 0]);
    $delivery = NotificationWebhookDelivery::factory()->create(['subscription_id' => $subscription->id, 'quota_reserved' => true]);
    runNotificationWebhook($delivery);
    runNotificationWebhook($delivery);
    Http::assertSentCount(1);
    Http::assertSent(function ($request) use ($subscription, $delivery): bool {
        return $request->header('X-NRO-Event-ID')[0] === $delivery->event_key
            && $request->header('X-NRO-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->header('X-NRO-Timestamp')[0].'.'.$request->body(), $subscription->webhook_secret);
    });
    expect($delivery->fresh()->status)->toBe('delivered')->and($delivery->fresh()->attempts)->toBe(1);
});
test('retries do not consume extra quota and terminal failures refund only once', function (): void {
    Http::fake(['*' => Http::response('', 500)]);
    $subscription = NotificationSubscription::factory()->create(['billing_type' => 'usage', 'remaining_uses' => 0]);
    $delivery = NotificationWebhookDelivery::factory()->create(['subscription_id' => $subscription->id, 'quota_reserved' => true]);
    for ($i = 0; $i < 5; $i++) {
        runNotificationWebhook($delivery);
    }
    runNotificationWebhook($delivery);
    Http::assertSentCount(5);
    expect($delivery->fresh()->status)->toBe('failed')->and($subscription->fresh()->remaining_uses)->toBe(1)->and($delivery->fresh()->quota_reserved)->toBeFalse();
});
test('expired queued events refund quota without HTTP', function (): void {
    $subscription = NotificationSubscription::factory()->create(['expires_at' => now()->subSecond(), 'billing_type' => 'usage', 'remaining_uses' => 0]);
    $delivery = NotificationWebhookDelivery::factory()->create(['subscription_id' => $subscription->id, 'quota_reserved' => true]);
    runNotificationWebhook($delivery);
    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('failed')->and($subscription->fresh()->remaining_uses)->toBe(1);
});
test('private and reserved webhook URLs are rejected including DNS rebinding', function (string $url): void {
    expect(app(PublicWebhookUrl::class)->endpoint($url))->toBeNull();
})->with(['http://127.0.0.1', 'http://10.0.0.1', 'http://169.254.169.254/latest', 'http://100.64.1.1', 'http://224.0.0.1', 'http://[::1]', 'http://[::ffff:8.8.8.8]', 'https://8.8.8.8:444/', 'ftp://8.8.8.8', 'http://user:pass@8.8.8.8', 'http://8.8.8.8/#secret', 'http://198.51.100.1']);
test('destination DNS is checked again before sending and mixed public private records are blocked', function (): void {
    $rule = new class extends PublicWebhookUrl
    {
        public bool $private = false;

        protected function addresses(string $host): array
        {
            return $this->private ? ['8.8.8.8', '127.0.0.1'] : ['8.8.8.8'];
        }
    };
    expect($rule->endpoint('https://receiver.example/webhook'))->not->toBeNull();
    $rule->private = true;
    $subscription = NotificationSubscription::factory()->create(['webhook_url' => 'https://receiver.example/webhook']);
    $delivery = NotificationWebhookDelivery::factory()->create(['subscription_id' => $subscription->id]);
    (new DeliverNotificationWebhook($delivery->id))->handle($rule, app(NotificationWebhookService::class));
    Http::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('failed');
});
test('redirects are not followed and a later retry can succeed', function (): void {
    Http::fakeSequence()->push('', 302, ['Location' => 'http://127.0.0.1'])->push('', 200);
    $delivery = NotificationWebhookDelivery::factory()->create();
    runNotificationWebhook($delivery);
    expect($delivery->fresh()->status)->toBe('pending');
    runNotificationWebhook($delivery);
    expect($delivery->fresh()->status)->toBe('delivered');
    Http::assertSentCount(2);
});
test('ingest records each unique event once and outbox snapshots survive notification pruning', function (): void {
    $this->seed(NroNotificationSeeder::class);
    NotificationSubscription::factory()->create();
    $service = app(NroNotificationService::class);
    $payload = ['server_code' => 1, 'code' => 'OTHER', 'content' => 'Custom notification', 'occurred_at' => now()->toISOString(), 'event_id' => 'event1'];
    $result = $service->ingest($payload);
    $service->ingest($payload);
    $this->assertDatabaseCount('notification_webhook_deliveries', 1);
    $result['notify']->forceDelete();
    expect(NotificationWebhookDelivery::query()->first()->payload['content'])->toBe('Custom notification');
});
test('outbox and quota roll back if ingestion transaction fails', function (): void {
    $subscription = NotificationSubscription::factory()->create(['billing_type' => 'usage', 'remaining_uses' => 1]);
    try {
        DB::transaction(function (): void {
            app(NotificationWebhookService::class)->capture(str_repeat('c', 64), ['content' => 'Rollback']);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }
    $this->assertDatabaseCount('notification_webhook_deliveries', 0);
    expect($subscription->fresh()->remaining_uses)->toBe(1);
});
test('scheduler recovers pending and interrupted deliveries', function (): void {
    $pending = NotificationWebhookDelivery::factory()->create(['updated_at' => now()->subMinutes(3)]);
    $interrupted = NotificationWebhookDelivery::factory()->create(['status' => 'processing', 'updated_at' => now()->subMinutes(3)]);
    NotificationWebhookDelivery::factory()->create(['status' => 'processing']);
    $this->artisan('nro:retry-webhooks')->assertSuccessful();
    Queue::assertPushed(DeliverNotificationWebhook::class, 2);
    expect($interrupted->fresh()->status)->toBe('pending');
});
