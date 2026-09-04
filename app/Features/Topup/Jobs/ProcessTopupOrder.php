<?php

namespace App\Features\Topup\Jobs;

use App\Enums\OrderStatus;
use App\Features\Topup\Services\OrderStatusService;
use App\Features\Topup\Services\TopupService;
use App\Mail\Orders\OrderFailedMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProcessTopupOrder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
        $this->onQueue('topup');
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(TopupService $topupService): void
    {
        $topupService->process($this->orderId);
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order instanceof Order || $order->order_status === OrderStatus::Completed) {
            return;
        }

        app(OrderStatusService::class)->transition($order, OrderStatus::Failed, 'Không thể xử lý topup sau nhiều lần thử.');
        Mail::to($order->email)->queue(new OrderFailedMail($order->refresh()));
    }
}
