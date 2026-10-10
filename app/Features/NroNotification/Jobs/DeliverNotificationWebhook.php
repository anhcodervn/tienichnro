<?php

namespace App\Features\NroNotification\Jobs;

use App\Features\NroNotification\Services\NotificationWebhookService;
use App\Models\NotificationSubscription;
use App\Models\NotificationWebhookDelivery;
use App\Rules\PublicWebhookUrl;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverNotificationWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return 'notification-webhook:'.$this->deliveryId;
    }

    public function handle(PublicWebhookUrl $urlRule, NotificationWebhookService $service): void
    {
        $claimed = NotificationWebhookDelivery::query()->whereKey($this->deliveryId)->where('status', 'pending')->update(['status' => 'processing', 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $delivery = NotificationWebhookDelivery::query()->findOrFail($this->deliveryId);
        if ($delivery->attempts >= 5) {
            $service->finish($delivery->id, 'failed', error: 'Maximum webhook attempts reached.');

            return;
        }
        $delivery->increment('attempts');
        $subscription = NotificationSubscription::query()->find($delivery->subscription_id);
        if (! $subscription || $subscription->status !== 'active' || ($subscription->expires_at !== null && $subscription->expires_at->isPast())) {
            $service->finish($delivery->id, 'failed', error: 'Subscription is inactive or expired.');

            return;
        }
        $endpoint = $urlRule->endpoint($subscription->webhook_url ?? '');
        if ($endpoint === null || ! defined('CURLOPT_RESOLVE')) {
            $service->finish($delivery->id, 'failed', error: 'Webhook destination is not public or cURL is unavailable.');

            return;
        }
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $ip = str_contains($endpoint['ip'], ':') ? '['.$endpoint['ip'].']' : $endpoint['ip'];
        try {
            $response = Http::withOptions(['allow_redirects' => false, 'proxy' => '', 'curl' => [CURLOPT_RESOLVE => [$endpoint['host'].':'.$endpoint['port'].':'.$ip]]])
                ->connectTimeout(5)->timeout(15)->withHeaders([
                    'X-NRO-Event-ID' => $delivery->event_key, 'X-NRO-Timestamp' => $timestamp,
                    'X-NRO-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $subscription->webhook_secret),
                ])->withBody($body, 'application/json')->post($subscription->webhook_url);
            $service->finish($delivery->id, $response->successful() ? 'delivered' : ($delivery->attempts >= 5 ? 'failed' : 'pending'), $response->status(), $response->successful() ? null : 'Webhook returned a non-success status.');
        } catch (Throwable) {
            $service->finish($delivery->id, $delivery->attempts >= 5 ? 'failed' : 'pending', error: 'Webhook connection failed.');
        }
    }
}
