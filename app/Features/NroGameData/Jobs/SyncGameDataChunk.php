<?php

namespace App\Features\NroGameData\Jobs;

use App\Features\NroGameData\Services\GameDataSyncService;
use App\Models\NroGameDataSync;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGameDataChunk implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 60;

    public int $expiresAt;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(public int $syncId, public int $chunkIndex, public array $rows)
    {
        $this->expiresAt = now()->addHours(2)->timestamp;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function retryUntil(): DateTimeInterface
    {
        return CarbonImmutable::createFromTimestamp($this->expiresAt);
    }

    public function handle(GameDataSyncService $service): void
    {
        if (! $service->process($this->syncId, $this->chunkIndex, $this->rows)) {
            $this->release(5);
        }
    }

    public function failed(?Throwable $exception): void
    {
        NroGameDataSync::query()->whereKey($this->syncId)->whereIn('status', ['queued', 'processing'])
            ->update(['status' => 'failed', 'last_error' => 'A game data chunk failed. Inspect queue logs before resubmitting.']);
        Log::error('Game data sync failed.', ['sync_id' => $this->syncId, 'chunk' => $this->chunkIndex]);
    }
}
