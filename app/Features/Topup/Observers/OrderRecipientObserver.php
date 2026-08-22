<?php

namespace App\Features\Topup\Observers;

use App\Features\Reporting\Services\TopupDiscordReporterService;
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

        if (! $recipient->wasChanged([
            'status',
            'provider_status',
            'completed_at',
            'failed_at',
        ])) {
            return;
        }

        OrderStatusUpdated::dispatch($recipient->order()->firstOrFail());
    }
}
