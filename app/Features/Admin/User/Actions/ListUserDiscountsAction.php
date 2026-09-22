<?php

namespace App\Features\Admin\User\Actions;

use App\Models\User;
use App\Models\UserGlobalPackagePrice;
use App\Models\UserPackagePrice;
use Illuminate\Database\Eloquent\Builder;

class ListUserDiscountsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $users = $this->discountedUserQuery($filters)
            ->withCount([
                'packagePrices',
                'packagePrices as active_package_prices_count' => fn (Builder $query) => $query->where('is_active', true),
                'globalPackagePrices',
                'globalPackagePrices as active_global_package_prices_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
        $userIds = $users->getCollection()->modelKeys();
        $packageRules = UserPackagePrice::query()
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'pricing_mode', 'discount_basis_points', 'is_active', 'updated_at']);
        $globalRules = UserGlobalPackagePrice::query()
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'pricing_mode', 'discount_basis_points', 'is_active', 'updated_at']);
        $rulesByUser = $packageRules->concat($globalRules)->groupBy('user_id');

        return [
            'data' => $users->getCollection()->map(function (User $user) use ($rulesByUser): array {
                $rules = $rulesByUser->get($user->id, collect());
                $discountRates = $rules
                    ->where('is_active', true)
                    ->where('pricing_mode', UserPackagePrice::MODE_DISCOUNT)
                    ->pluck('discount_basis_points')
                    ->filter(fn (mixed $basisPoints): bool => $basisPoints !== null)
                    ->map(fn (mixed $basisPoints): float => ((int) $basisPoints) / 100)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
                $updatedAt = $rules->max('updated_at');

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status === 'banned' ? 'blocked' : $user->status,
                    'package_rules_count' => (int) $user->package_prices_count,
                    'active_package_rules_count' => (int) $user->active_package_prices_count,
                    'global_rules_count' => (int) $user->global_package_prices_count,
                    'active_global_rules_count' => (int) $user->active_global_package_prices_count,
                    'pricing_modes' => $rules->pluck('pricing_mode')->unique()->values()->all(),
                    'discount_rates' => $discountRates,
                    'updated_at' => $updatedAt?->toISOString(),
                ];
            })->all(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'stats' => [
                'discounted_users' => $this->discountedUserQuery()->count(),
                'active_users' => $this->discountedUserQuery(['rule_status' => 'active'])->count(),
                'package_rules' => UserPackagePrice::query()->whereHas('user')->count(),
                'global_rules' => UserGlobalPackagePrice::query()->whereHas('user')->count(),
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function discountedUserQuery(array $filters = []): Builder
    {
        return User::query()
            ->where(function (Builder $query): void {
                $query->whereHas('packagePrices')->orWhereHas('globalPackagePrices');
            })
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function (Builder $searchQuery) use ($search): void {
                    if (is_numeric($search)) {
                        $searchQuery->orWhereKey((int) $search);
                    }

                    $searchQuery
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when(($filters['scope'] ?? null) === 'packages', fn (Builder $query) => $query->whereHas('packagePrices'))
            ->when(($filters['scope'] ?? null) === 'global', fn (Builder $query) => $query->whereHas('globalPackagePrices'))
            ->when(($filters['rule_status'] ?? null) === 'active', function (Builder $query): void {
                $query->where(function (Builder $activeQuery): void {
                    $activeQuery
                        ->whereHas('packagePrices', fn (Builder $ruleQuery) => $ruleQuery->where('is_active', true))
                        ->orWhereHas('globalPackagePrices', fn (Builder $ruleQuery) => $ruleQuery->where('is_active', true));
                });
            })
            ->when(($filters['rule_status'] ?? null) === 'inactive', function (Builder $query): void {
                $query
                    ->whereDoesntHave('packagePrices', fn (Builder $ruleQuery) => $ruleQuery->where('is_active', true))
                    ->whereDoesntHave('globalPackagePrices', fn (Builder $ruleQuery) => $ruleQuery->where('is_active', true));
            });
    }
}
