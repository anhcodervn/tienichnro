<?php

namespace App\Features\Client\GameVerification\Services;

use App\Features\Admin\Setting\Services\ToolAvailabilityService;
use App\Features\Client\GameVerification\Requests\GameVerificationHistoryRequest;
use App\Features\Client\GameVerification\Resources\GameVerificationOrderResource;
use App\Models\User;
use App\Support\EventStreamRuntime;
use Generator;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameVerificationFeedService
{
    public function __construct(private readonly GameVerificationOrderService $orders, private readonly ToolAvailabilityService $tools, private readonly EventStreamRuntime $runtime) {}

    public function response(GameVerificationHistoryRequest $request, bool $admin = false): StreamedResponse
    {
        abort_unless($request->user()?->status === 'active', 403);
        $viewer = $request->user();
        $filters = $request->validated();
        if ($request->hasSession()) {
            $request->session()->save();
        }

        return response()->eventStream(function () use ($viewer, $filters, $admin): Generator {
            echo "retry: 3000\n\n";
            yield from $this->events($viewer, $filters, $admin);
        }, endStreamWith: null);
    }

    public function snapshot(User $viewer, array $filters, bool $admin = false): array
    {
        $orders = $this->orders->history($filters, $admin ? null : $viewer->id);
        if ($admin) {
            return GameVerificationOrderResource::collection($orders)->response()->getData(true);
        }
        $orders->withPath(route('tools.game-verification'))->appends(Arr::except($filters, ['page']));

        return ['html' => view('pages.tools.partials.game-verification-history', [
            'orders' => $orders, 'statuses' => GameVerificationOrderResource::STATUSES, 'progressLabels' => GameVerificationOrderResource::PROGRESS,
        ])->render()];
    }

    /** @return Generator<int, StreamedEvent> */
    public function events(User $viewer, array $filters, bool $admin = false, ?float $durationSeconds = null, int $pollMicroseconds = 2000000): Generator
    {
        $deadline = microtime(true) + ($durationSeconds ?? $this->runtime->durationSeconds());
        $signature = null;
        do {
            if (connection_aborted()) {
                return;
            }
            if (! User::query()->whereKey($viewer->id)->where('status', 'active')->when($admin, fn ($query) => $query->where('role', 'admin'))->exists()) {
                yield new StreamedEvent('access-expired', []);

                return;
            }
            if (! $admin && ! ($this->tools->find('account_verification')['is_enabled'] ?? false)) {
                yield new StreamedEvent('maintenance', []);

                return;
            }
            $snapshot = $this->snapshot($viewer, $filters, $admin);
            $current = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            if ($signature !== $current) {
                $signature = $current;
                yield new StreamedEvent('orders', $snapshot);
            } else {
                yield new StreamedEvent('heartbeat', []);
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
