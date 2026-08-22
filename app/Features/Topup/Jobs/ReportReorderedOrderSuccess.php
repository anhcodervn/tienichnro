<?php

namespace App\Features\Topup\Jobs;

use App\Enums\OrderStatus;
use App\Features\Reporting\Services\TopupDiscordReporterService;
use App\Features\Topup\Services\TopupProviderBalanceService;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ReportReorderedOrderSuccess implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 55;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $orderId,
        public readonly int $reorderAttempt,
    ) {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return $this->orderId.':'.$this->reorderAttempt;
    }

    public function handle(
        TopupProviderBalanceService $providerBalanceService,
        TopupDiscordReporterService $discordReporter,
    ): void {
        $order = Order::query()->with('provider')->find($this->orderId);

        if (! $order instanceof Order
            || $order->order_status !== OrderStatus::Completed
            || (int) data_get($order->metadata, 'reorder.attempt') !== $this->reorderAttempt
            || data_get($order->metadata, 'reorder.status') !== 'queued') {
            return;
        }

        $balanceBefore = data_get($order->metadata, 'reorder.balance_before');
        $currency = (string) data_get($order->metadata, 'reorder.currency');

        if (! is_numeric($balanceBefore) || $currency === '') {
            return;
        }

        $balanceAfter = $providerBalanceService->forOrder($order);
        $discordReporter->reorderSucceeded(
            order: $order,
            attempt: $this->reorderAttempt,
            adminId: (int) data_get($order->metadata, 'reorder.requested_by'),
            balanceBefore: (int) $balanceBefore,
            balanceAfter: $balanceAfter->balance,
            currency: $balanceAfter->currency,
        );
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof Throwable) {
            report($exception);
        }
    }
}
