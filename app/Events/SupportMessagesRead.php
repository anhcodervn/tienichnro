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

class SupportMessagesRead implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly int $tenantId;

    /**
     * @param  array<int, int>  $messageIds
     * @param  array{user_unread:int,admin_unread:int}  $stats
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $conversationId,
        public readonly array $messageIds,
        public readonly string $readerRole,
        public readonly string $readAt,
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
        return 'support.messages.read';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'message_ids' => $this->messageIds,
            'reader_role' => $this->readerRole,
            'read_at' => $this->readAt,
            'stats' => $this->stats,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'default';
    }
}
