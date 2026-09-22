<?php

namespace App\Features\Admin\User\Services;

use App\Features\Admin\User\Resources\AdminUserResource;
use App\Features\Admin\WalletTransaction\Resources\AdminWalletTransactionResource;
use App\Models\User;
use App\Models\UserLog;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminUserService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paginateUsers(array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $users = $this->userQuery($filters)
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => AdminUserResource::collection($users->getCollection())->resolve(),
            'meta' => $this->paginationMeta($users),
            'stats' => [
                'total_users' => User::query()->count(),
                'new_today' => User::query()->whereDate('created_at', today())->count(),
                'active_users' => User::query()->where('status', 'active')->count(),
                'blocked_users' => User::query()->where('status', 'banned')->count(),
                'total_user_wallet_balance' => (float) Wallet::query()
                    ->where('type', Wallet::TYPE_MAIN)
                    ->whereIn('user_id', User::query()->where('role', '!=', 'admin')->select('id'))
                    ->sum('balance'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userDetail(User $user): array
    {
        $user = User::query()
            ->with('wallet')
            ->withCount('orders')
            ->withSum([
                'orders as paid_orders_total' => fn (Builder $query) => $query->where('payment_status', 'paid'),
            ], 'total_amount')
            ->findOrFail($user->id);

        $wallet = $user->wallet;

        return [
            'user' => $user,
            'wallet' => $wallet,
            'stats' => [
                'total_spent' => (float) ($user->paid_orders_total ?? 0),
                'order_count' => $user->orders_count,
                'completed_order_count' => $user->orders()->where('order_status', 'completed')->count(),
            ],
            'latest_login' => [
                'at' => $user->last_login_at?->toISOString(),
                'ip' => $user->last_login_ip,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paginateUserWalletTransactions(User $user, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $wallet = $user->wallet()->first();

        if (! $wallet instanceof Wallet) {
            return $this->emptyPagination($perPage);
        }

        $transactions = WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->with(['wallet.user'])
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('description', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['type'] ?? null), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => AdminWalletTransactionResource::collection($transactions->getCollection())->resolve(),
            'meta' => $this->paginationMeta($transactions),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function paginateUserLogs(User $user, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        $logs = UserLog::query()
            ->where('user_id', $user->id)
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['action'] ?? null), fn (Builder $query) => $query->where('action', $filters['action']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'data' => $logs->getCollection()->map(fn (UserLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'description' => $log->description,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toISOString(),
            ])->all(),
            'meta' => $this->paginationMeta($logs),
        ];
    }

    private function userQuery(array $filters): Builder
    {
        return User::query()
            ->with('wallet')
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $builder) use ($search): void {
                    if (is_numeric($search)) {
                        $builder->orWhere('id', (int) $search);
                    }

                    $builder
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(filled($filters['status'] ?? null), function (Builder $query) use ($filters): void {
                $status = $filters['status'] === 'blocked' ? 'banned' : $filters['status'];
                $query->where('status', $status);
            })
            ->when(filled($filters['role'] ?? null), fn (Builder $query) => $query->where('role', $filters['role']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id');
    }

    /**
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPagination(int $perPage): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
            ],
        ];
    }
}
