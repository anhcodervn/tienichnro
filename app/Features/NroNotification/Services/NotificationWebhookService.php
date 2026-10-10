<?php

namespace App\Features\NroNotification\Services;

use App\Features\NroNotification\Jobs\DeliverNotificationWebhook;
use App\Models\NotificationSubscription;
use App\Models\NotificationWebhookDelivery;
use Illuminate\Support\Facades\DB;

class NotificationWebhookService
{
    /** @param array<string, mixed> $payload */
    public function capture(string $eventKey, array $payload): void
    {
        NotificationSubscription::query()->where('mode', 'webhook')->where('status', 'active')->orderBy('id')->chunkById(100, function ($subscriptions) use ($eventKey, $payload): void {
            foreach ($subscriptions as $subscription) {
                DB::transaction(function () use ($subscription, $eventKey, $payload): void {
                    $subscription = NotificationSubscription::query()->lockForUpdate()->find($subscription->id);
                    if (! $subscription?->hasAccess() || NotificationWebhookDelivery::query()->where('subscription_id', $subscription->id)->where('event_key', $eventKey)->exists()) {
                        return;
                    }
                    $reserved = $subscription->billing_type === 'usage';
                    $delivery = NotificationWebhookDelivery::query()->create(['subscription_id' => $subscription->id, 'event_key' => $eventKey, 'payload' => $payload, 'status' => 'pending', 'quota_reserved' => $reserved]);
                    if ($reserved) {
                        $subscription->decrement('remaining_uses');
                    }
                    DeliverNotificationWebhook::dispatch($delivery->id)->afterCommit();
                });
            }
        });
    }

    public function finish(int $id, string $status, ?int $responseStatus = null, ?string $error = null): void
    {
        DB::transaction(function () use ($id, $status, $responseStatus, $error): void {
            $subscriptionId = NotificationWebhookDelivery::query()->whereKey($id)->value('subscription_id');
            $subscription = NotificationSubscription::query()->lockForUpdate()->find($subscriptionId);
            $delivery = NotificationWebhookDelivery::query()->lockForUpdate()->find($id);
            if (! $delivery || $delivery->status !== 'processing') {
                return;
            }
            if ($status === 'failed' && $delivery->quota_reserved) {
                $subscription?->increment('remaining_uses');
                $delivery->quota_reserved = false;
            }
            $delivery->fill(['status' => $status, 'response_status' => $responseStatus, 'last_error' => $error, 'delivered_at' => $status === 'delivered' ? now() : null])->save();
        });
    }
}
