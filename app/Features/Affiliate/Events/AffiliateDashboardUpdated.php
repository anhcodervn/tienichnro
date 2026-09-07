<?php

namespace App\Features\Affiliate\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AffiliateDashboardUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly string $updatedAt;

    public function __construct(public readonly int $userId)
    {
        $this->updatedAt = now()->toISOString();
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("users.{$this->userId}.affiliate")];
    }

    public function broadcastAs(): string
    {
        return 'affiliate.dashboard.updated';
    }

    /** @return array{updated_at: string} */
    public function broadcastWith(): array
    {
        return ['updated_at' => $this->updatedAt];
    }
}
