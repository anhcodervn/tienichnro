<?php

namespace App\Jobs;

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordGatewayService;
use App\Features\Client\GamePassword\Services\GamePasswordOrderService;
use App\Models\GamePasswordOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ChangeGamePassword implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $orderId, public readonly int $attemptVersion = 1)
    {
        $this->onConnection('game_password');
        $this->onQueue('game-password');
    }

    public function handle(GamePasswordOrderService $orders, GamePasswordGatewayService $gateway): void
    {
        $version = $this->attemptVersion ?? 1;
        $order = $orders->start($this->orderId, $version);
        if (! $order) {
            $this->delete();

            return;
        }
        try {
            $arguments = [$order->username, $order->old_password, $order->new_password, $order->server_code,
                fn (string $phase) => $orders->progress($order->id, $phase, $version)];
            $arguments[] = (bool) $order->verify_before_change;
            $arguments[] = ['key' => $order->captcha_api_key, 'secret' => $order->captcha_api_secret];
            $gateway->change(...$arguments);
            $orders->finish($order->id, 'success', attemptVersion: $version);
        } catch (GamePasswordFailure $exception) {
            $orders->finish($order->id, 'error', $exception->reason, attemptVersion: $version);
        } catch (Throwable) {
            $this->failed(null);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $order = GamePasswordOrder::find($this->orderId);
        if ($order && $order->attempt_version === ($this->attemptVersion ?? 1) && in_array($order->status, ['pending', 'processing'], true)) {
            app(GamePasswordOrderService::class)->finish($order->id, 'error', 'JOB_INTERRUPTED', attemptVersion: $this->attemptVersion ?? 1);
        }
    }
}
