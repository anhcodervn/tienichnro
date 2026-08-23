<?php

namespace App\Features\Topup\Observers;

use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Features\Topup\Events\AdminTopupOrderUpdated;
use App\Features\Topup\Events\OrderStatusUpdated;
use App\Models\OrderRecipient;

class OrderRecipientObserver
{
    public function __construct(private readonly TopupDiscordReporterService $discordReporter) {}

    public function updated(OrderRecipient $recipient): void
    {
        if (($recipient->wasChanged('status') && $recipient->status === 'failed')
            || ($recipient->wasChanged('failure_reason') && filled($recipient->failure_reason))) {
            $this->discordReporter->recipientNeedsAttention($recipient);
        }

        $publicStatusChanged = $recipient->wasChanged([
            'status',
            'provider_status',
            'completed_at',
            'failed_at',
        ]);
        $adminVisibleStateChanged = $recipient->wasChanged([
            'status',
            'provider_status',
            'provider_reference',
            'provider_response',
            'failure_reason',
            'status_check_attempts',
            'submitted_at',
            'last_checked_at',
            'completed_at',
            'failed_at',
        ]);

        if (! $publicStatusChanged && ! $adminVisibleStateChanged) {
            return;
        }

        $order = $recipient->order()->firstOrFail();

        if ($publicStatusChanged) {
            OrderStatusUpdated::dispatch($order);
        }

        if ($adminVisibleStateChanged) {
            AdminTopupOrderUpdated::dispatch($order);
        }
    }
}
