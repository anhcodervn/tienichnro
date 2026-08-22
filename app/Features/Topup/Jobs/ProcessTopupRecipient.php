<?php

namespace App\Features\Topup\Jobs;

use App\Features\Topup\Enums\TopupProviderStatus;
use App\Features\Topup\Services\RecipientFulfillmentService;
use App\Models\OrderRecipient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class ProcessTopupRecipient implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 100;

    public int $maxExceptions = 4;

    public int $timeout = 55;

    /** @var array<int, int> */
    public array $backoff = [5, 20, 60, 120];

    public function __construct(public readonly int $recipientId, public readonly int $unit = 1)
    {
        $this->afterCommit();
        $this->onQueue('topup');
    }

    public function uniqueId(): string
    {
        return $this->recipientId.':'.$this->unit;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new RateLimited('topup-provider')];
    }

    public function handle(RecipientFulfillmentService $service): void
    {
        $service->submit($this->recipientId, $this->unit);
    }

    public function failed(Throwable $exception): void
    {
        $recipient = OrderRecipient::query()->find($this->recipientId);

        if (! $recipient instanceof OrderRecipient || in_array($recipient->status, ['completed', 'failed', 'cancelled'], true)) {
            return;
        }

        $recipient->forceFill([
            'status' => 'processing',
            'provider_status' => TopupProviderStatus::Processing->value,
            'failure_reason' => 'Hệ thống xử lý đang quá tải hoặc mất kết nối; cần đối soát thủ công.',
            'last_checked_at' => now(),
        ])->save();
    }
}
