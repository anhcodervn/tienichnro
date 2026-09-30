<?php

namespace App\Features\Admin\GameService\Services;

use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GameServiceOrderChatService
{
    /** @param array<string, mixed> $filters */
    public function adminThreads(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $orders = GameServiceOrder::query()
            ->with(['user:id,username,full_name,email,avatar', 'collaborator:id,username,full_name,avatar', 'latestMessage.sender:id,username,full_name'])
            ->withCount('messages')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('code', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('service_name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 30), 1), 100));

        return [
            'data' => $orders->getCollection()->map(fn (GameServiceOrder $order): array => $this->orderPayload($order))->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function collaboratorOrders(User $collaborator): array
    {
        abort_unless($collaborator->canAccessCollaboratorDashboard(), 403);

        return GameServiceOrder::query()
            ->whereBelongsTo($collaborator, 'collaborator')
            ->with(['user:id,username,full_name,email,avatar', 'collaborator:id,username,full_name,avatar', 'latestMessage.sender:id,username,full_name'])
            ->withCount('messages')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (GameServiceOrder $order): array => $this->orderPayload($order))
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function collaborators(?int $gameServiceId = null, ?int $includeUserId = null): array
    {
        return User::query()
            ->where('role', User::ROLE_COLLABORATOR)
            ->where('status', 'active')
            ->when($gameServiceId !== null, fn (Builder $query) => $query->where(function (Builder $permissionQuery) use ($gameServiceId, $includeUserId): void {
                $permissionQuery->whereHas(
                    'allowedGameServices',
                    fn (Builder $serviceQuery) => $serviceQuery->whereKey($gameServiceId),
                );

                if ($includeUserId !== null) {
                    $permissionQuery->orWhere(
                        $permissionQuery->getModel()->getQualifiedKeyName(),
                        $includeUserId,
                    );
                }
            }))
            ->orderBy('username')
            ->get(['id', 'username', 'full_name', 'avatar'])
            ->map(fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'username' => (string) $user->username,
                'avatar' => $user->avatar,
            ])->all();
    }

    /** @return array<string, mixed> */
    public function thread(GameServiceOrder $order, User $actor): array
    {
        $this->authorize($order, $actor);
        $order->loadMissing(['user:id,username,full_name,email,avatar', 'collaborator:id,username,full_name,avatar']);

        return [
            'order' => $this->orderPayload($order),
            'messages' => $order->messages()
                ->with('sender:id,username,full_name,avatar')
                ->latest('id')
                ->limit(200)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (GameServiceOrderMessage $message): array => $this->messagePayload($message))
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function send(GameServiceOrder $order, User $actor, string $content): array
    {
        $this->authorize($order, $actor);

        $message = DB::transaction(function () use ($order, $actor, $content): GameServiceOrderMessage {
            $lockedOrder = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            $this->authorize($lockedOrder, $actor);

            return $lockedOrder->messages()->create([
                'sender_id' => $actor->id,
                'sender_role' => $this->senderRole($lockedOrder, $actor),
                'message' => trim($content),
            ]);
        }, 3);

        return $this->messagePayload($message->load('sender:id,username,full_name,avatar'));
    }

    private function authorize(GameServiceOrder $order, User $actor): void
    {
        $isActiveCollaborator = $order->collaborator_id === $actor->id
            && $actor->canAccessCollaboratorDashboard();

        abort_unless(
            $actor->role === 'admin'
            || $order->user_id === $actor->id
            || $isActiveCollaborator,
            403,
        );
    }

    private function senderRole(GameServiceOrder $order, User $actor): string
    {
        if ($actor->role === 'admin') {
            return GameServiceOrderMessage::ROLE_ADMIN;
        }

        return $order->collaborator_id === $actor->id
            ? GameServiceOrderMessage::ROLE_COLLABORATOR
            : GameServiceOrderMessage::ROLE_USER;
    }

    /** @return array<string, mixed> */
    private function orderPayload(GameServiceOrder $order): array
    {
        return [
            'id' => (int) $order->id,
            'code' => (string) $order->code,
            'service_name' => (string) $order->service_name,
            'package_name' => (string) $order->package_name,
            'status' => (string) $order->status,
            'user' => $order->user ? ['id' => (int) $order->user->id, 'name' => $order->user->name, 'email' => (string) $order->user->email] : null,
            'collaborator' => $order->collaborator ? ['id' => (int) $order->collaborator->id, 'name' => $order->collaborator->name] : null,
            'messages_count' => (int) ($order->messages_count ?? $order->messages()->count()),
            'last_message' => $order->latestMessage ? $this->messagePayload($order->latestMessage) : null,
            'created_at' => $order->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function messagePayload(GameServiceOrderMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'sender_id' => $message->sender_id ? (int) $message->sender_id : null,
            'sender_role' => (string) $message->sender_role,
            'sender_name' => $message->sender?->name ?? 'Tài khoản đã xóa',
            'message' => (string) $message->message,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
