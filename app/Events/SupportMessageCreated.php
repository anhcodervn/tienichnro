<?php

namespace App\Events;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportMessageCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $conversation
     * @param  array{user_unread:int,admin_unread:int}  $stats
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $message,
        public readonly array $conversation,
        public readonly array $stats,
    ) {
        $this->tenantId = app(TenantContext::class)->isSchemaReady()
            ? (int) User::query()->withoutGlobalScopes()->whereKey($userId)->value('tenant_id')
            : 0;
    }

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("users.{$this->userId}.support"),
            new PrivateChannel("admin.sites.{$this->tenantId}.support"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'support.message.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'conversation' => $this->conversation,
            'stats' => $this->stats,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
