<?php

namespace App\Jobs;

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GameVerification\Services\GameVerificationGatewayService;
use App\Features\Client\GameVerification\Services\GameVerificationOrderService;
use App\Models\GameVerificationOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class VerifyGameAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $orderId, public readonly int $attemptVersion = 1)
    {
        $this->onConnection('game_verification');
        $this->onQueue('game-verification');
    }

    public function handle(GameVerificationOrderService $orders, GameVerificationGatewayService $gateway): void
    {
        $version = $this->attemptVersion ?? 1;
        $order = $orders->start($this->orderId, $version);
        if (! $order) {
            $this->delete();

            return;
        }
        try {
            $syntax = $gateway->verify($order->username, $order->old_password, $order->server_code,
                fn (string $phase) => $orders->progress($order->id, $phase, $version),
                ['key' => $order->captcha_api_key, 'secret' => $order->captcha_api_secret]);
            $orders->finish($order->id, 'success', attemptVersion: $version, syntax: $syntax);
        } catch (GamePasswordFailure $exception) {
            $orders->finish($order->id, 'error', $exception->reason, attemptVersion: $version);
        } catch (Throwable) {
            $this->failed(null);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $order = GameVerificationOrder::find($this->orderId);
        if ($order && $order->attempt_version === ($this->attemptVersion ?? 1) && in_array($order->status, ['pending', 'processing'], true)) {
            app(GameVerificationOrderService::class)->finish($order->id, 'error', 'JOB_INTERRUPTED', attemptVersion: $this->attemptVersion ?? 1);
        }
    }
}
