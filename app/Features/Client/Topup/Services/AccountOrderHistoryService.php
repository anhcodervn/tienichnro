<?php

namespace App\Features\Client\Topup\Services;

use App\Models\Game;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class AccountOrderHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $sort = (string) ($filters['sort'] ?? 'newest');

        return Order::query()
            ->select([
                'id', 'code', 'user_id', 'game_id', 'game_server_id', 'game_account',
                'package_name', 'quantity', 'total_amount', 'payment_method',
                'payment_status', 'order_status', 'created_at',
            ])
            ->whereBelongsTo($user)
            ->with(['game:id,name', 'server:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('game_account', 'like', "%{$search}%")
                        ->orWhere('package_name', 'like', "%{$search}%")
                        ->orWhereHas('game', fn (Builder $gameQuery): Builder => $gameQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['game_id']), fn (Builder $query): Builder => $query->where('game_id', (int) $filters['game_id']))
            ->when(filled($filters['payment_status'] ?? null), fn (Builder $query): Builder => $query->where('payment_status', $filters['payment_status']))
            ->when(filled($filters['order_status'] ?? null), fn (Builder $query): Builder => $query->where('order_status', $filters['order_status']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query): Builder => $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay()))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query): Builder => $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay()))
            ->when($sort === 'oldest', fn (Builder $query): Builder => $query->orderBy('created_at')->orderBy('id'))
            ->when($sort === 'amount_desc', fn (Builder $query): Builder => $query->orderByDesc('total_amount')->orderByDesc('id'))
            ->when($sort === 'amount_asc', fn (Builder $query): Builder => $query->orderBy('total_amount')->orderBy('id'))
            ->when($sort === 'newest', fn (Builder $query): Builder => $query->latest('created_at')->orderByDesc('id'))
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();
    }

    /** @return Collection<int, Game> */
    public function filterGames(User $user): Collection
    {
        return Game::query()
            ->select(['id', 'name'])
            ->whereHas('orders', fn (Builder $query): Builder => $query->whereBelongsTo($user))
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }
}
