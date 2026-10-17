<?php

namespace App\Features\NroNotification\Jobs;

use App\Features\NroNotification\Services\ZaloBotNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendZaloEquipmentNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 25;

    /** @param array{kind: string, char_name: string, item: string, location: string|null} $equipment */
    public function __construct(public array $equipment, public int $serverCode, public string $serverName) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(ZaloBotNotificationService $service): void
    {
        $service->sendNotiZalo($this->equipment, $this->serverCode, $this->serverName);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Zalo equipment notification delivery failed.', [
            'char_name' => $this->equipment['char_name'], 'server_code' => $this->serverCode,
        ]);
    }
}
