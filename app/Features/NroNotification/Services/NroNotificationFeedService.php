<?php

namespace App\Features\NroNotification\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use Generator;
use Illuminate\Http\StreamedEvent;
use Throwable;

class NroNotificationFeedService
{
    public function __construct(private readonly NroNotificationService $notifications, private readonly ToolAvailabilityService $services) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{html: string, pagination: string, count: int, total: int, per_page: int, current_page: int, last_page: int, signature: string, server_time: string}
     */
    public function snapshot(array $filters): array
    {
        $filters['limit'] = $filters['limit'] ?? $filters['per_page'] ?? 10;
        $filters['page'] = $filters['page'] ?? 1;
        unset($filters['per_page']);
        $notifies = $this->notifications->paginate($filters);
        $notifies->withPath(route('nro.notifies.page'))->appends($filters);
        $html = view('pages.nro.partials.notification-list', ['notifies' => $notifies])->render();
        $pagination = $notifies->onEachSide(1)->links('pages.nro.partials.notification-pagination')->toHtml();

        return ['html' => $html, 'pagination' => $pagination, 'count' => $notifies->count(), 'total' => $notifies->total(),
            'per_page' => $notifies->perPage(), 'current_page' => $notifies->currentPage(), 'last_page' => $notifies->lastPage(),
            'signature' => hash('sha256', $html.$pagination), 'server_time' => now()->toISOString()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Generator<int, StreamedEvent>
     */
    public function events(array $filters, float $durationSeconds = 25, int $pollMicroseconds = 2000000): Generator
    {
        $deadline = microtime(true) + $durationSeconds;
        $signature = null;

        try {
            do {
                if (connection_aborted()) {
                    return;
                }
                $tool = $this->services->interaction('game_notifications');
                if (! $tool['is_enabled']) {
                    yield new StreamedEvent('maintenance', ['message' => $tool['maintenance_message']]);

                    return;
                }
                $snapshot = $this->snapshot($filters);
                if ($snapshot['signature'] !== $signature) {
                    $signature = $snapshot['signature'];
                    yield new StreamedEvent('notifications', $snapshot);
                } else {
                    yield new StreamedEvent('heartbeat', ['server_time' => $snapshot['server_time']]);
                }
                if (microtime(true) >= $deadline) {
                    return;
                }
                if ($pollMicroseconds > 0) {
                    usleep($pollMicroseconds);
                }
            } while (microtime(true) < $deadline);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
