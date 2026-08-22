<?php

namespace App\Features\Reporting\Jobs;

use App\Features\Reporting\Services\DiscordReportService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendDiscordReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 12;

    public int $uniqueFor = 900;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, string|int|float|bool|null>  $details
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $title,
        public readonly array $details,
        public readonly string $dedupeKey,
    ) {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return hash('sha256', $this->channel.'|'.$this->dedupeKey);
    }

    public function handle(DiscordReportService $reportService): void
    {
        $reportService->sendNow($this->channel, $this->title, $this->details);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof Throwable) {
            report($exception);
        }
    }
}
