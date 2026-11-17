<?php

namespace App\Features\Client\GameImage\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Models\User;
use App\Support\EventStreamRuntime;
use Generator;
use Illuminate\Http\StreamedEvent;

class GameImageAccountFeedService
{
    public function __construct(private readonly GameImageAccountListService $accounts, private readonly GameImageOrderService $orders, private readonly ToolAvailabilityService $tools, private readonly EventStreamRuntime $runtime) {}

    /** @param array<string, mixed> $filters
     * @return array{html: string, signature: string, total: int}
     */
    public function snapshot(User $user, array $filters): array
    {
        $accounts = $this->accounts->paginate($user, $filters);
        $html = view('pages.tools.partials.game-image-account-table', [
            'accounts' => $accounts, 'filters' => $filters, 'capturePrice' => $this->orders->configuration()['price'],
        ])->render();

        return ['html' => $html, 'signature' => hash('sha256', $html), 'total' => $accounts->total()];
    }

    /** @param array<string, mixed> $filters
     * @return Generator<int, StreamedEvent>
     */
    public function events(User $user, array $filters, ?float $durationSeconds = null, int $pollMicroseconds = 2000000): Generator
    {
        $deadline = microtime(true) + ($durationSeconds ?? $this->runtime->durationSeconds());
        $signature = null;
        do {
            if (connection_aborted()) {
                return;
            }
            if (! User::query()->whereKey($user->id)->where('status', 'active')->exists()) {
                yield new StreamedEvent('access-expired', []);

                return;
            }
            if (! ($this->tools->find('game_image')['is_enabled'] ?? true)) {
                yield new StreamedEvent('maintenance', []);

                return;
            }
            $snapshot = $this->snapshot($user, $filters);
            if ($snapshot['signature'] !== $signature) {
                $signature = $snapshot['signature'];
                yield new StreamedEvent('accounts', $snapshot);
            } else {
                yield new StreamedEvent('heartbeat', ['server_time' => now()->toISOString()]);
            }
            if (microtime(true) >= $deadline) {
                return;
            }
            if ($pollMicroseconds > 0) {
                usleep($pollMicroseconds);
            }
        } while (microtime(true) < $deadline);
    }
}
