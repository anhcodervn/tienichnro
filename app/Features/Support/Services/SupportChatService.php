<?php

namespace App\Features\Support\Services;

use App\Events\SupportConversationUpdated;
use App\Events\SupportMessageCreated;
use App\Events\SupportMessagesRead;
use App\Features\Support\Resources\SupportConversationResource;
use App\Features\Support\Resources\SupportMessageResource;
use App\Jobs\SendSupportMessageToDiscord;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;

class SupportChatService
{
    public const MESSAGE_PAGE_SIZE = 20;

    /** @param array{cursor?:string} $filters */
    public function clientThread(User $user, array $filters): array
    {
        $conversation = SupportConversation::query()
            ->whereBelongsTo($user)
            ->with(['user', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_role', SupportMessage::ROLE_ADMIN)
                ->whereNull('read_at')])
            ->first();

        if (! $conversation instanceof SupportConversation) {
            return $this->emptyThread();
        }

        return $this->threadPayload($conversation, $filters['cursor'] ?? null);
    }

    /** @param array{search?:string,per_page?:int,page?:int} $filters */
    public function adminConversations(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 30);
        $search = trim((string) ($filters['search'] ?? ''));

        $conversations = SupportConversation::query()
            ->with(['user:id,username,email,full_name,avatar', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_role', SupportMessage::ROLE_USER)
                ->whereNull('read_at')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where(function (Builder $matchingUser) use ($search): void {
                        if (is_numeric($search)) {
                            $matchingUser->orWhere('id', (int) $search);
                        }

                        $matchingUser
                            ->orWhere('full_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'conversations' => SupportConversationResource::collection($conversations->getCollection())->resolve(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
            'stats' => $this->unreadStats(),
        ];
    }

    /** @param array{cursor?:string} $filters */
    public function adminThread(int $conversationId, array $filters): array
    {
        $conversation = $this->adminConversation($conversationId);

        return $this->threadPayload($conversation, $filters['cursor'] ?? null);
    }

    public function sendAsUser(User $user, string $content): array
    {
        $conversation = SupportConversation::query()->createOrFirst(
            $this->conversationIdentity($user),
            ['status' => SupportConversation::STATUS_OPEN],
        );

        return $this->createMessage($conversation, $user, SupportMessage::ROLE_USER, $content);
    }

    public function sendAsAdmin(User $admin, int $conversationId, string $content): array
    {
        return $this->createMessage(
            $this->adminConversation($conversationId),
            $admin,
            SupportMessage::ROLE_ADMIN,
            $content,
        );
    }

    public function startAsAdmin(User $admin, int $userId, string $content): array
    {
        $user = User::query()->where('role', SupportMessage::ROLE_USER)->findOrFail($userId);
        $conversation = SupportConversation::query()->createOrFirst(
            $this->conversationIdentity($user),
            ['status' => SupportConversation::STATUS_OPEN],
        );

        return $this->createMessage($conversation, $admin, SupportMessage::ROLE_ADMIN, $content);
    }

    public function markClientRead(User $user): array
    {
        $conversation = SupportConversation::query()->whereBelongsTo($user)->first();

        if (! $conversation instanceof SupportConversation) {
            return ['updated' => 0, 'stats' => $this->unreadStats()];
        }

        return $this->markRead($conversation, SupportMessage::ROLE_USER);
    }

    public function markAdminRead(int $conversationId): array
    {
        return $this->markRead($this->adminConversation($conversationId), SupportMessage::ROLE_ADMIN);
    }

    /** @return array<int, array<string, mixed>> */
    public function searchUsers(string $search): array
    {
        $search = trim($search);

        return User::query()
            ->where('role', SupportMessage::ROLE_USER)
            ->where(function (Builder $query) use ($search): void {
                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }

                $query
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->with('supportConversation:id,user_id')
            ->latest('id')
            ->limit(20)
            ->get(['id', 'username', 'email', 'full_name', 'avatar'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'email' => (string) $user->email,
                'avatar' => $user->avatar,
                'conversation_id' => $user->supportConversation?->id,
            ])
            ->all();
    }

    /** @return array{user_unread:int,admin_unread:int} */
    public function unreadStats(?User $user = null): array
    {
        $userUnread = 0;

        if ($user instanceof User) {
            $userUnread = SupportMessage::query()
                ->whereHas('conversation', fn (Builder $query) => $query->whereBelongsTo($user))
                ->where('sender_role', SupportMessage::ROLE_ADMIN)
                ->whereNull('read_at')
                ->count();
        }

        return [
            'user_unread' => $userUnread,
            'admin_unread' => SupportMessage::query()
                ->where('sender_role', SupportMessage::ROLE_USER)
                ->whereNull('read_at')
                ->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function createMessage(SupportConversation $conversation, User $sender, string $senderRole, string $content): array
    {
        $message = DB::transaction(function () use ($conversation, $sender, $senderRole, $content): SupportMessage {
            $lockedConversation = SupportConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $messageAttributes = [
                'sender_id' => $sender->id,
                'sender_role' => $senderRole,
                'message' => $content,
            ];

            if (app(TenantContext::class)->isActive()) {
                $messageAttributes['tenant_id'] = $lockedConversation->tenant_id;
            }

            $message = $lockedConversation->messages()->create($messageAttributes);

            $lockedConversation->update([
                'status' => SupportConversation::STATUS_OPEN,
                'last_message_at' => $message->created_at,
            ]);

            $stats = $this->unreadStats($lockedConversation->user);
            $messagePayload = $this->messagePayload($message);
            $conversationUnread = SupportMessage::query()
                ->whereBelongsTo($lockedConversation, 'conversation')
                ->where('sender_role', SupportMessage::ROLE_USER)
                ->whereNull('read_at')
                ->count();
            $conversationPayload = $this->conversationSummary($lockedConversation, $message, $conversationUnread);

            SupportMessageCreated::dispatch($lockedConversation->user_id, $messagePayload, $conversationPayload, $stats);
            SupportConversationUpdated::dispatch($lockedConversation->user_id, $conversationPayload, $stats);

            if ($senderRole === SupportMessage::ROLE_USER) {
                SendSupportMessageToDiscord::dispatch($message->id)->onQueue('default')->afterCommit();
            }

            return $message;
        });

        $conversation = $this->adminConversation($conversation->id);

        return [
            'conversation' => SupportConversationResource::make($conversation)->resolve(),
            'message' => SupportMessageResource::make($message)->resolve(),
            'stats' => $this->unreadStats($conversation->user),
        ];
    }

    /** @return array<string, int|null> */
    private function conversationIdentity(User $user): array
    {
        $identity = ['user_id' => $user->id];

        if (app(TenantContext::class)->isActive()) {
            $identity['tenant_id'] = $user->tenant_id;
        }

        return $identity;
    }

    /** @return array<string, mixed> */
    private function markRead(SupportConversation $conversation, string $readerRole): array
    {
        $incomingRole = $readerRole === SupportMessage::ROLE_ADMIN
            ? SupportMessage::ROLE_USER
            : SupportMessage::ROLE_ADMIN;
        $readAt = now();

        $messageIds = DB::transaction(function () use ($conversation, $incomingRole, $readAt): array {
            $ids = SupportMessage::query()
                ->whereBelongsTo($conversation, 'conversation')
                ->where('sender_role', $incomingRole)
                ->whereNull('read_at')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($ids !== []) {
                SupportMessage::query()
                    ->whereBelongsTo($conversation, 'conversation')
                    ->where('sender_role', $incomingRole)
                    ->whereNull('read_at')
                    ->whereKey($ids)
                    ->update(['read_at' => $readAt, 'updated_at' => $readAt]);
            }

            return $ids;
        });

        $stats = $this->unreadStats($conversation->user);

        if ($messageIds !== []) {
            SupportMessagesRead::dispatch(
                $conversation->user_id,
                $conversation->id,
                $messageIds,
                $readerRole,
                $readAt->toISOString(),
                $stats,
            );
        }

        return ['updated' => count($messageIds), 'message_ids' => $messageIds, 'stats' => $stats];
    }

    private function adminConversation(int $conversationId): SupportConversation
    {
        return SupportConversation::query()
            ->with(['user', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_role', SupportMessage::ROLE_USER)
                ->whereNull('read_at')])
            ->findOrFail($conversationId);
    }

    /** @return array<string, mixed> */
    private function threadPayload(SupportConversation $conversation, ?string $encodedCursor): array
    {
        $cursor = filled($encodedCursor) ? Cursor::fromEncoded($encodedCursor) : null;
        $messages = SupportMessage::query()
            ->whereBelongsTo($conversation, 'conversation')
            ->orderByDesc('id')
            ->cursorPaginate(self::MESSAGE_PAGE_SIZE, ['*'], 'cursor', $cursor);

        return [
            'conversation' => SupportConversationResource::make($conversation)->resolve(),
            'messages' => SupportMessageResource::collection(collect($messages->items())->reverse()->values())->resolve(),
            'meta' => [
                'per_page' => self::MESSAGE_PAGE_SIZE,
                'next_cursor' => $messages->nextCursor()?->encode(),
                'has_more' => $messages->hasMorePages(),
            ],
            'stats' => $this->unreadStats($conversation->user),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyThread(): array
    {
        return [
            'conversation' => null,
            'messages' => [],
            'meta' => ['per_page' => self::MESSAGE_PAGE_SIZE, 'next_cursor' => null, 'has_more' => false],
            'stats' => ['user_unread' => 0, 'admin_unread' => $this->unreadStats()['admin_unread']],
        ];
    }

    /** @return array<string, mixed> */
    private function messagePayload(SupportMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'conversation_id' => (int) $message->support_conversation_id,
            'sender_id' => (int) $message->sender_id,
            'sender_role' => (string) $message->sender_role,
            'message' => (string) $message->message,
            'read_at' => $message->read_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function conversationSummary(SupportConversation $conversation, SupportMessage $message, int $adminUnread): array
    {
        $user = $conversation->user;

        return [
            'id' => (int) $conversation->id,
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'username' => (string) $user->username,
                'avatar' => $user->avatar,
            ],
            'status' => (string) $conversation->status,
            'last_message' => $this->messagePayload($message),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'unread_count' => $message->sender_role === SupportMessage::ROLE_USER ? $adminUnread : 0,
        ];
    }
}
