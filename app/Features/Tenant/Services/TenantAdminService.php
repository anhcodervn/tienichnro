<?php

namespace App\Features\Tenant\Services;

use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantAdminService
{
    public function __construct(
        private readonly TopupPackagePricingService $topupPackagePricingService,
        private readonly TenantContext $tenantContext,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $now = CarbonImmutable::now((string) config('app.timezone'));
        $todayStartsAt = $now->startOfDay();
        $tomorrowStartsAt = $todayStartsAt->addDay();
        $monthStartsAt = $now->startOfMonth();
        $nextMonthStartsAt = $monthStartsAt->addMonth();
        $sites = Tenant::query()
            ->select(['id', 'name', 'slug', 'billing_user_id', 'status', 'is_main', 'allow_below_cost', 'created_at'])
            ->with([
                'domains:id,tenant_id,domain,is_primary,is_verified',
                'billingUser:id,username,email',
                'billingUser.wallet:id,tenant_id,user_id,balance',
            ])
            ->withCount([
                'users',
                'orders',
                'orders as orders_today_count' => fn (Builder $query) => $query
                    ->where('created_at', '>=', $todayStartsAt)
                    ->where('created_at', '<', $tomorrowStartsAt),
                'orders as orders_month_count' => fn (Builder $query) => $query
                    ->where('created_at', '>=', $monthStartsAt)
                    ->where('created_at', '<', $nextMonthStartsAt),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('domains', fn (Builder $domainQuery) => $domainQuery->where('domain', 'like', "%{$search}%"))
                    ->orWhereHas('billingUser', fn (Builder $userQuery) => $userQuery
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            }))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['site_type'] ?? null) === 'main', fn (Builder $query) => $query->where('is_main', true))
            ->when(($filters['site_type'] ?? null) === 'child', fn (Builder $query) => $query->where('is_main', false))
            ->orderByDesc('is_main')
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->withQueryString();

        $sites->setCollection($sites->getCollection()->map(fn (Tenant $tenant): array => $this->serialize($tenant)));

        return $sites;
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload): Tenant
    {
        return DB::transaction(function () use ($payload): Tenant {
            $billingUser = $this->resolveBillingUser((int) $payload['billing_user_id']);

            $tenant = Tenant::query()->create([
                'name' => $payload['name'], 'slug' => strtolower((string) $payload['slug']),
                'billing_user_id' => $billingUser->id, 'status' => 'active',
                'allow_below_cost' => (bool) ($payload['allow_below_cost'] ?? false),
            ]);
            $tenant->domains()->create([
                'domain' => strtolower((string) $payload['domain']), 'is_primary' => true, 'is_verified' => true,
            ]);
            User::query()->create([
                'tenant_id' => $tenant->id,
                'username' => $billingUser->username,
                'email' => $billingUser->email,
                'phone' => $billingUser->phone,
                'full_name' => $billingUser->full_name,
                'avatar' => $billingUser->avatar,
                'password' => $billingUser->password,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => $billingUser->email_verified_at,
            ]);

            return $tenant->load(['domains', 'billingUser']);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function update(Tenant $tenant, array $payload): Tenant
    {
        abort_if($tenant->is_main && isset($payload['status']) && $payload['status'] !== 'active', 422, 'Không thể tạm ngừng website chính.');

        return DB::transaction(function () use ($tenant, $payload): Tenant {
            if (isset($payload['billing_user_id'])) {
                $this->resolveBillingUser((int) $payload['billing_user_id']);
            }

            $tenant->fill(collect($payload)->only(['name', 'slug', 'billing_user_id', 'status', 'allow_below_cost'])->all())->save();

            if (isset($payload['domain'])) {
                TenantDomain::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'is_primary' => true],
                    ['domain' => strtolower((string) $payload['domain']), 'is_verified' => true],
                );
            }

            return $tenant->refresh()->load(['domains', 'billingUser']);
        }, 3);
    }

    /** @return array<int, array<string, mixed>> */
    public function prices(Tenant $tenant): array
    {
        $packages = TopupPackage::query()->with('game:id,name')->where('status', 'active')
            ->orderBy('game_id')->orderBy('denomination')->get();

        return $this->tenantContext->run($tenant, function () use ($packages, $tenant): array {
            $rules = TenantPackagePrice::query()->where('tenant_id', $tenant->id)->get()->keyBy('topup_package_id');
            $this->topupPackagePricingService->apply($packages);

            return $packages->map(function (TopupPackage $package) use ($rules): array {
                $rule = $rules->get($package->id);

                return [
                    'package_id' => $package->id, 'game' => $package->game?->name, 'package' => $package->name,
                    'denomination' => $package->denomination, 'cost_price' => (int) $package->tenant_cost_price,
                    'selling_price' => (int) $package->selling_price, 'profit' => (int) $package->tenant_profit,
                    'pricing_mode' => $rule?->pricing_mode ?? 'markup_amount', 'fixed_price' => $rule?->fixed_price,
                    'markup_amount' => $rule?->markup_amount ?? 0,
                    'markup_percentage' => $rule?->markup_basis_points === null ? null : $rule->markup_basis_points / 100,
                    'is_active' => $rule?->is_active ?? true,
                ];
            })->all();
        });
    }

    /** @param array<string, mixed> $payload */
    public function updatePrice(Tenant $tenant, TopupPackage $package, array $payload): TenantPackagePrice
    {
        $rule = TenantPackagePrice::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'topup_package_id' => $package->id],
            [
                'pricing_mode' => $payload['pricing_mode'],
                'fixed_price' => $payload['pricing_mode'] === 'fixed' ? $payload['fixed_price'] : null,
                'markup_amount' => $payload['pricing_mode'] === 'markup_amount' ? $payload['markup_amount'] : null,
                'markup_basis_points' => $payload['pricing_mode'] === 'markup_percentage' ? (int) round((float) $payload['markup_percentage'] * 100) : null,
                'is_active' => (bool) $payload['is_active'],
            ],
        );
        $this->tenantContext->run($tenant, fn () => $this->topupPackagePricingService->resolve($package));

        return $rule;
    }

    /** @return array<string, mixed> */
    public function serialize(Tenant $tenant): array
    {
        $billingUser = $tenant->billingUser;

        return [
            'id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug, 'status' => $tenant->status,
            'is_main' => $tenant->is_main, 'allow_below_cost' => $tenant->allow_below_cost,
            'domain' => $tenant->domains->firstWhere('is_primary', true)?->domain,
            'billing_user' => $billingUser === null ? null : ['id' => $billingUser->id, 'username' => $billingUser->username, 'email' => $billingUser->email],
            'billing_balance' => $billingUser === null ? null : (int) ($billingUser->wallet?->balance ?? 0),
            'users_count' => (int) ($tenant->users_count ?? 0),
            'orders_count' => (int) ($tenant->orders_count ?? 0),
            'orders_today_count' => (int) ($tenant->orders_today_count ?? 0),
            'orders_month_count' => (int) ($tenant->orders_month_count ?? 0),
            'created_at' => $tenant->created_at?->toISOString(),
        ];
    }

    private function resolveBillingUser(int $userId): User
    {
        $mainTenant = $this->tenantContext->mainTenant();
        $billingUser = User::query()
            ->withoutGlobalScopes()
            ->whereKey($userId)
            ->where('tenant_id', $mainTenant?->id)
            ->where('role', 'user')
            ->where('status', 'active')
            ->first();

        if (! $billingUser instanceof User) {
            throw ValidationException::withMessages([
                'billing_user_id' => 'Hãy chọn một tài khoản người dùng đang hoạt động trên NapCarot.',
            ]);
        }

        return $billingUser;
    }
}
