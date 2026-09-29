<?php

namespace App\Features\Client\Affiliate\Services;

use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateAnnouncementRead;
use App\Models\AffiliateProfile;
use App\Models\GameServiceOrder;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CollaboratorDashboardService
{
    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $this->assertActive($user);
        $orders = GameServiceOrder::query()->whereBelongsTo($user, 'collaborator');
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => Wallet::TYPE_AFFILIATE],
            ['tenant_id' => $user->tenant_id, 'balance' => 0, 'hold_balance' => 0, 'total_recharge' => 0, 'total_spent' => 0],
        );

        return [
            'orders' => [
                'pending' => (clone $orders)->where('status', 'pending')->count(),
                'processing' => (clone $orders)->where('status', 'processing')->count(),
                'review' => (clone $orders)->where('status', 'review')->count(),
                'completed' => (clone $orders)->where('status', 'completed')->count(),
                'total' => (clone $orders)->count(),
            ],
            'revenue' => [
                'order_revenue' => (int) (clone $orders)->sum('collaborator_total_cost'),
                'held' => (int) (clone $orders)->whereIn('status', ['pending', 'processing', 'review'])->sum('collaborator_total_cost'),
                'settled' => (int) (clone $orders)->sum('collaborator_settlement_amount'),
                'available' => (int) $wallet->balance,
                'withdrawal_hold' => (int) $wallet->hold_balance,
            ],
            'unread_announcements' => $this->unreadAnnouncements($user),
            'recent_orders' => (clone $orders)->latest('id')->limit(5)->get()->map(fn (GameServiceOrder $order): array => $this->orderPayload($order))->all(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function orders(User $user, array $filters): array
    {
        $this->assertActive($user);
        $search = trim((string) ($filters['search'] ?? ''));
        $paginator = GameServiceOrder::query()
            ->whereBelongsTo($user, 'collaborator')
            ->when($search !== '', fn (Builder $query) => $query->where('code', 'like', "%{$search}%"))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 30), 1), 100));

        $games = GameServiceOrder::query()->whereBelongsTo($user, 'collaborator')
            ->whereNotNull('game_id')->select(['game_id', 'game_name'])->distinct()->orderBy('game_name')->get()
            ->map(fn (GameServiceOrder $order): array => ['id' => (int) $order->game_id, 'name' => $order->game_name])->values()->all();

        return [
            'data' => $paginator->getCollection()->map(fn (GameServiceOrder $order): array => $this->orderPayload($order))->all(),
            'games' => $games,
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    public function startOrder(User $user, GameServiceOrder $order): GameServiceOrder
    {
        $this->assertActive($user);

        return DB::transaction(function () use ($user, $order): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->collaborator_id === $user->id, 403);
            abort_unless($locked->status === 'pending', 422, 'Chỉ có thể nhận đơn đang chờ xử lý.');
            $locked->forceFill(['status' => 'processing', 'processing_at' => now()])->save();

            return $locked->refresh();
        }, 3);
    }

    public function submitOrder(User $user, GameServiceOrder $order): GameServiceOrder
    {
        $this->assertActive($user);

        return DB::transaction(function () use ($user, $order): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless($locked->collaborator_id === $user->id, 403);
            abort_unless($locked->status === 'processing', 422, 'Chỉ có thể gửi duyệt đơn đang xử lý.');
            $locked->forceFill(['status' => 'review'])->save();

            return $locked->refresh();
        }, 3);
    }

    public function readAnnouncement(User $user, AffiliateAnnouncement $announcement): int
    {
        $this->assertActive($user);
        abort_unless($announcement->is_published && $announcement->published_at?->lte(now()), 404);
        AffiliateAnnouncementRead::query()->firstOrCreate(
            ['affiliate_announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return $this->unreadAnnouncements($user);
    }

    private function unreadAnnouncements(User $user): int
    {
        return AffiliateAnnouncement::query()
            ->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();
    }

    private function assertActive(User $user): void
    {
        abort_unless($user->status === 'active' && AffiliateProfile::query()->where('user_id', $user->id)->where('status', 'active')->exists(), 403);
    }

    /** @return array<string, mixed> */
    private function orderPayload(GameServiceOrder $order): array
    {
        return [
            'code' => $order->code, 'game_id' => $order->game_id, 'game_name' => $order->game_name,
            'service_name' => $order->service_name, 'package_name' => $order->package_name, 'server_name' => $order->server_name,
            'payload' => $order->payload ?? [], 'quantity' => $order->quantity, 'status' => $order->status, 'collaborator_amount' => $order->collaborator_total_cost,
            'settled_at' => $order->collaborator_settled_at?->toISOString(), 'created_at' => $order->created_at?->toISOString(),
        ];
    }
}
