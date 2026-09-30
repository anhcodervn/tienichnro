<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateAnnouncementRead;
use App\Models\AffiliateProfile;
use App\Models\AffiliateWithdrawal;
use App\Models\GameServiceOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Support\EditorContentRenderer;
use App\Support\GameServicePayloadCipher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CollaboratorDashboardService
{
    public function __construct(
        private readonly EditorContentRenderer $contentRenderer,
        private readonly GameServicePayloadCipher $payloadCipher,
        private readonly AffiliateWalletService $affiliateWalletService,
    ) {}

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $this->assertActive($user);
        $visibleOrders = $this->visibleOrders($user);
        $assignedOrders = GameServiceOrder::query()->whereBelongsTo($user, 'collaborator');
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => Wallet::TYPE_COLLABORATOR],
            ['tenant_id' => $user->tenant_id, 'balance' => 0, 'hold_balance' => 0, 'work_hold_balance' => 0, 'total_recharge' => 0, 'total_spent' => 0],
        );

        return [
            'orders' => [
                'pending' => (clone $visibleOrders)->where('status', 'pending')->count(),
                'processing' => (clone $visibleOrders)->where('status', 'processing')->count(),
                'review' => (clone $visibleOrders)->where('status', 'review')->count(),
                'completed' => (clone $visibleOrders)->where('status', 'completed')->count(),
                'total' => (clone $visibleOrders)->count(),
            ],
            'revenue' => [
                'order_revenue' => (int) (clone $assignedOrders)->sum('collaborator_total_cost'),
                'held' => (int) $wallet->work_hold_balance,
                'settled' => (int) (clone $assignedOrders)->sum('collaborator_settlement_amount'),
                'available' => (int) $wallet->balance,
                'withdrawal_hold' => (int) $wallet->hold_balance,
            ],
            'unread_announcements' => $this->unreadAnnouncements($user),
            'recent_orders' => (clone $visibleOrders)->latest('id')->limit(5)->get()
                ->map(fn (GameServiceOrder $order): array => $this->orderPayload($order, $user))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function financialData(User $user): array
    {
        $this->assertActive($user);
        $profile = $this->profile($user);
        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => Wallet::TYPE_COLLABORATOR],
            ['tenant_id' => $user->tenant_id, 'balance' => 0, 'hold_balance' => 0, 'work_hold_balance' => 0, 'total_recharge' => 0, 'total_spent' => 0],
        );
        $withdrawals = AffiliateWithdrawal::query()
            ->where('user_id', $user->id)
            ->where('wallet_type', AffiliateWithdrawal::WALLET_COLLABORATOR)
            ->latest('id')->limit(20)->get();

        return [
            'minimum_withdrawal' => AffiliateWalletService::MINIMUM_COLLABORATOR_WITHDRAWAL,
            'wallet' => [
                'balance' => (int) $wallet->balance,
                'hold_balance' => (int) $wallet->hold_balance,
                'work_hold_balance' => (int) $wallet->work_hold_balance,
            ],
            'profile' => [
                'status' => $profile->status,
                'bank_name' => $profile->bank_name,
                'bank_account_name' => $profile->bank_account_name,
                'bank_account_number_masked' => $this->mask((string) $profile->bank_account_number),
                'has_payout_account' => filled($profile->bank_name) && filled($profile->bank_account_name) && filled($profile->bank_account_number),
            ],
            'withdrawals' => $withdrawals->map(fn (AffiliateWithdrawal $withdrawal): array => [
                'id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'bank_name' => $withdrawal->bank_name,
                'account_number' => $this->mask((string) $withdrawal->bank_account_number),
                'created_at' => $withdrawal->created_at?->toISOString(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function announcements(User $user): array
    {
        $this->assertActive($user);
        $announcements = AffiliateAnnouncement::query()
            ->where('audience', AffiliateAnnouncement::AUDIENCE_COLLABORATOR)
            ->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('is_pinned')
            ->latest('published_at')
            ->latest('id')
            ->limit(50)
            ->withExists(['reads as is_read' => fn ($query) => $query->where('user_id', $user->id)])
            ->get(['id', 'title', 'content', 'is_pinned', 'published_at'])
            ->map(fn (AffiliateAnnouncement $announcement): array => $this->announcementPayload($announcement))
            ->all();

        return [
            'announcements' => $announcements,
            'unread_count' => collect($announcements)->where('is_read', false)->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function walletHistory(User $user): array
    {
        $this->assertActive($user);
        $wallet = $this->affiliateWalletService->wallet($user, Wallet::TYPE_COLLABORATOR);
        $paginator = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate(30);

        return [
            'wallet' => [
                'balance' => (int) $wallet->balance,
                'hold_balance' => (int) $wallet->hold_balance,
                'work_hold_balance' => (int) $wallet->work_hold_balance,
            ],
            'data' => $paginator->getCollection()->map(function (WalletTransaction $transaction): array {
                $metadata = $transaction->metadata ?? [];

                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'event' => $metadata['event'] ?? null,
                    'amount' => (int) $transaction->amount,
                    'balance_before' => (int) $transaction->balance_before,
                    'balance_after' => (int) $transaction->balance_after,
                    'work_hold_before' => isset($metadata['work_hold_before']) ? (int) $metadata['work_hold_before'] : null,
                    'work_hold_after' => isset($metadata['work_hold_after']) ? (int) $metadata['work_hold_after'] : null,
                    'order_code' => $metadata['order_code'] ?? null,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at?->toISOString(),
                ];
            })->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function readAnnouncement(User $user, AffiliateAnnouncement $announcement): int
    {
        $this->assertActive($user);
        abort_unless(
            $announcement->audience === AffiliateAnnouncement::AUDIENCE_COLLABORATOR
                && $announcement->is_published
                && $announcement->published_at?->lte(now()),
            404,
        );

        AffiliateAnnouncementRead::query()->firstOrCreate(
            ['affiliate_announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );

        return $this->unreadAnnouncements($user);
    }

    /** @param array<string, mixed> $payload */
    public function updatePayout(User $user, array $payload): AffiliateProfile
    {
        $this->assertActive($user);
        $profile = $this->profile($user);
        $profile->fill($payload)->save();

        return $profile->refresh();
    }

    /** @param array<string, mixed> $filters */
    public function orders(User $user, array $filters): array
    {
        $this->assertActive($user);
        $search = trim((string) ($filters['search'] ?? ''));
        $paginator = $this->visibleOrders($user)
            ->when($search !== '', fn (Builder $query) => $query->where('code', 'like', "%{$search}%"))
            ->when(filled($filters['game_id'] ?? null), fn (Builder $query) => $query->where('game_id', $filters['game_id']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 30), 1), 100));

        $games = $this->visibleOrders($user)
            ->whereNotNull('game_id')->select(['game_id', 'game_name'])->distinct()->orderBy('game_name')->get()
            ->map(fn (GameServiceOrder $order): array => ['id' => (int) $order->game_id, 'name' => $order->game_name])->values()->all();

        return [
            'data' => $paginator->getCollection()->map(fn (GameServiceOrder $order): array => $this->orderPayload($order, $user))->all(),
            'games' => $games,
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ];
    }

    public function startOrder(User $user, GameServiceOrder $order): GameServiceOrder
    {
        $this->assertActive($user);

        return DB::transaction(function () use ($user, $order): GameServiceOrder {
            $locked = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_if($locked->collaborator_id !== null && $locked->collaborator_id !== $user->id, 403);
            abort_unless($locked->status === 'pending', 422, 'Chỉ có thể nhận đơn đang chờ xử lý.');

            if ($locked->collaborator_id === null && $user->role === User::ROLE_COLLABORATOR) {
                abort_unless(
                    $locked->game_service_id !== null
                        && $user->allowedGameServices()->whereKey($locked->game_service_id)->exists(),
                    403,
                    'Bạn chưa được cấp quyền nhận dịch vụ này.',
                );
            }

            $locked->forceFill([
                'collaborator_id' => $user->id,
                'status' => 'processing',
                'processing_at' => now(),
            ])->save();
            $this->affiliateWalletService->holdGameServiceOrder($locked);

            return $locked->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function orderData(GameServiceOrder $order, User $viewer): array
    {
        return $this->orderPayload($order, $viewer);
    }

    /** @return array<string, mixed> */
    public function orderPreview(User $user, GameServiceOrder $order): array
    {
        $this->assertActive($user);
        abort_unless($this->visibleOrders($user)->whereKey($order->getKey())->exists(), 403);
        abort_unless(in_array($order->status, ['pending', 'processing'], true), 422, 'Chỉ có thể xem đơn đang chờ hoặc đang làm.');

        $payload = $this->payloadCipher->decrypt((string) $order->getRawOriginal('payload'));
        $note = trim((string) ($payload['note'] ?? ''));

        return [
            ...Arr::except($this->orderPayload($order, $user), ['payload', 'payload_locked']),
            'customer_note' => $note !== '' ? $note : null,
        ];
    }

    /** @return array<string, mixed> */
    public function payload(User $user, GameServiceOrder $order): array
    {
        $this->assertActive($user);
        abort_unless(
            $user->role === User::ROLE_ADMIN
                || ($order->collaborator_id === $user->id && $order->status !== 'pending'),
            403,
        );

        return Arr::except(
            $this->payloadCipher->decrypt((string) $order->getRawOriginal('payload')),
            ['note'],
        );
    }

    private function unreadAnnouncements(User $user): int
    {
        return AffiliateAnnouncement::query()
            ->where('audience', AffiliateAnnouncement::AUDIENCE_COLLABORATOR)
            ->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereDoesntHave('reads', fn (Builder $query) => $query->where('user_id', $user->id))
            ->count();
    }

    /** @return array<string, mixed> */
    private function announcementPayload(AffiliateAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content_html' => $this->contentRenderer->renderNodes($announcement->content ?? [])->toHtml(),
            'is_pinned' => $announcement->is_pinned,
            'is_read' => (bool) $announcement->is_read,
            'published_at' => $announcement->published_at?->toISOString(),
        ];
    }

    private function assertActive(User $user): void
    {
        abort_unless($user->canAccessCollaboratorDashboard(), 403);
    }

    private function visibleOrders(User $user): Builder
    {
        return GameServiceOrder::query()->where(function (Builder $query) use ($user): void {
            $query->where('collaborator_id', $user->id)
                ->orWhere(function (Builder $availableQuery) use ($user): void {
                    $availableQuery->whereNull('collaborator_id')->where('status', 'pending');

                    if ($user->role === User::ROLE_COLLABORATOR) {
                        $availableQuery->whereIn(
                            'game_service_id',
                            $user->allowedGameServices()->select('game_services.id'),
                        );
                    }
                });
        });
    }

    private function profile(User $user): AffiliateProfile
    {
        return AffiliateProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['tenant_id' => $user->tenant_id, 'status' => 'active'],
        );
    }

    private function mask(string $value): string
    {
        return $value === '' ? '' : str_repeat('*', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }

    /** @return array<string, mixed> */
    private function orderPayload(GameServiceOrder $order, User $viewer): array
    {
        $isAssignedToViewer = $order->collaborator_id !== null && $order->collaborator_id === $viewer->id;

        return [
            'code' => $order->code, 'game_id' => $order->game_id, 'game_name' => $order->game_name,
            'service_name' => $order->service_name, 'package_name' => $order->package_name, 'server_name' => $order->server_name,
            'payload' => [], 'payload_locked' => $isAssignedToViewer, 'quantity' => $order->quantity, 'status' => $order->status,
            'collaborator_id' => $order->collaborator_id,
            'collaborator_amount' => $order->collaborator_total_cost, 'can_claim' => $order->status === 'pending' && ($order->collaborator_id === null || $isAssignedToViewer),
            'can_chat' => $isAssignedToViewer && $order->status !== 'pending',
            'settled_at' => $order->collaborator_settled_at?->toISOString(), 'created_at' => $order->created_at?->toISOString(),
            'available_at' => $order->collaborator_available_at?->toISOString(),
            'refunded_at' => $order->collaborator_refunded_at?->toISOString(),
        ];
    }
}
