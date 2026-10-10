<?php

namespace App\Console\Commands;

use App\Features\NroNotification\Jobs\DeliverNotificationWebhook;
use App\Models\NotificationWebhookDelivery;
use Illuminate\Console\Command;

class RetryNotificationWebhooks extends Command
{
    protected $signature = 'nro:retry-webhooks';

    protected $description = 'Recover pending notification webhook deliveries';

    public function handle(): int
    {
        NotificationWebhookDelivery::query()->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(2))->update(['status' => 'pending', 'updated_at' => now()->subMinute()]);
        NotificationWebhookDelivery::query()->where('status', 'pending')->where('updated_at', '<=', now()->subMinute())->orderBy('id')->chunkById(100, function ($deliveries): void {
            foreach ($deliveries as $delivery) {
                DeliverNotificationWebhook::dispatch($delivery->id);
            }
        });

        return self::SUCCESS;
    }
}
