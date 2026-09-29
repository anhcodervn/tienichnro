<?php

namespace App\Features\Admin\Affiliate\Services;

use App\Exceptions\ApiException;
use App\Features\Affiliate\Services\AffiliateCommissionService;
use App\Features\Affiliate\Services\AffiliateWalletService;
use App\Features\Topup\Services\TopupPackagePricingService;
use App\Models\AdminAuditLog;
use App\Models\AffiliateAnnouncement;
use App\Models\AffiliateCommission;
use App\Models\AffiliateGlobalPackageRate;
use App\Models\AffiliatePackageRate;
use App\Models\AffiliateProfile;
use App\Models\AffiliateProgram;
use App\Models\AffiliateWithdrawal;
use App\Models\GlobalTopupPackage;
use App\Models\Order;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\TopupPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Support\EditorContentRenderer;
use App\Support\TenantContext;
use App\Utils\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminAffiliateService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TopupPackagePricingService $pricingService,
        private readonly AffiliateWalletService $walletService,
        private readonly AffiliateCommissionService $commissionService,
        private readonly EditorContentRenderer $contentRenderer,
    ) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function overview(array $filters): array
    {
        $tenantId = $this->resolveTenantId($filters['site_id'] ?? null, true);
        $commissionQuery = AffiliateCommission::query()->withoutGlobalScope(TenantScope::class)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));
        $withdrawalQuery = AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));
        $profileQuery = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId));

        return [
            'partners' => [
                'total' => (clone $profileQuery)->count(),
                'active' => (clone $profileQuery)->where('status', 'active')->count(),
                'suspended' => (clone $profileQuery)->where('status', 'suspended')->count(),
            ],
            'commissions' => [
                'orders' => (clone $commissionQuery)->count(),
                'guest_orders' => (clone $commissionQuery)->whereNull('referred_user_id')->count(),
                'pending' => (int) (clone $commissionQuery)->where('status', 'pending')->whereNotNull('earned_at')->sum('amount'),
                'available' => (int) (clone $commissionQuery)->where('status', 'available')->sum('amount'),
                'reversed' => (int) (clone $commissionQuery)->where('status', 'reversed')->sum('amount'),
                'flagged' => (clone $commissionQuery)->where('is_flagged', true)->count(),
                'revenue' => (int) (clone $commissionQuery)
                    ->where(fn (Builder $query) => $query->where('status', 'available')
                        ->orWhere(fn (Builder $pendingQuery) => $pendingQuery->where('status', 'pending')->whereNotNull('earned_at')))
                    ->sum('base_amount'),
            ],
            'withdrawals' => [
                'requested' => (int) (clone $withdrawalQuery)->where('status', 'requested')->sum('amount'),
                'approved' => (int) (clone $withdrawalQuery)->where('status', 'approved')->sum('amount'),
                'paid' => (int) (clone $withdrawalQuery)->where('status', 'paid')->sum('amount'),
            ],
            'by_site' => $tenantId === null ? $this->siteOverview() : [],
            'selected_site_id' => $tenantId,
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function announcements(array $filters): array
    {
        $tenantId = $this->resolveTenantId($filters['site_id'] ?? null);

        return [
            'announcements' => AffiliateAnnouncement::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->with(['admin' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'username', 'full_name'])])
                ->orderByDesc('is_pinned')
                ->latest('published_at')
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (AffiliateAnnouncement $announcement): array => $this->serializeAnnouncement($announcement))
                ->all(),
            'sites' => $this->sites(),
            'selected_site_id' => $tenantId,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function saveAnnouncement(?int $announcementId, array $payload, User $admin, Request $request): AffiliateAnnouncement
    {
        return DB::transaction(function () use ($announcementId, $payload, $admin, $request): AffiliateAnnouncement {
            $announcement = $announcementId === null
                ? new AffiliateAnnouncement(['tenant_id' => $this->resolveTenantId($payload['site_id'] ?? null)])
                : AffiliateAnnouncement::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->findOrFail($announcementId);

            if ($announcement->exists) {
                $this->assertAdminTenant($announcement->tenant_id);
            }

            $old = $announcement->exists ? $announcement->getAttributes() : [];
            $wasPublished = (bool) $announcement->is_published;
            $isPublished = (bool) $payload['is_published'];
            $announcement->fill([
                'admin_id' => $admin->id,
                'title' => trim((string) $payload['title']),
                'content' => $payload['content'],
                'is_pinned' => (bool) $payload['is_pinned'],
                'is_published' => $isPublished,
                'published_at' => $isPublished && (! $wasPublished || $announcement->published_at === null) ? now() : $announcement->published_at,
            ])->save();
            $this->audit(
                $announcement->tenant_id,
                $admin,
                $announcementId === null ? 'affiliate_announcement_created' : 'affiliate_announcement_updated',
                $announcement,
                $old,
                $announcement->getAttributes(),
                $request,
            );

            return $announcement->refresh()->load('admin:id,username,full_name');
        }, 3);
    }

    public function deleteAnnouncement(int $announcementId, User $admin, Request $request): void
    {
        DB::transaction(function () use ($announcementId, $admin, $request): void {
            $announcement = AffiliateAnnouncement::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->findOrFail($announcementId);
            $this->assertAdminTenant($announcement->tenant_id);
            $old = $announcement->getAttributes();
            $this->audit($announcement->tenant_id, $admin, 'affiliate_announcement_deleted', $announcement, $old, [], $request);
            $announcement->delete();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function configuration(?int $requestedTenantId): array
    {
        $tenantId = $this->resolveTenantId($requestedTenantId);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $program = AffiliateProgram::query()->withoutGlobalScope(TenantScope::class)
            ->firstOrNew(['tenant_id' => $tenantId]);
        $rates = AffiliatePackageRate::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('topup_package_id');
        $globalRates = AffiliateGlobalPackageRate::query()->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('global_topup_package_id');
        $globalCatalog = GlobalTopupPackage::query()
            ->with(['gameSettings.game:id,name,package_mode,status'])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('denomination')
            ->get()
            ->filter(fn (GlobalTopupPackage $package): bool => $package->gameSettings->contains(
                fn ($setting): bool => $setting->game?->package_mode === 'global'
                    && $setting->game?->status === 'active'
                    && is_array($setting->receives)
                    && $setting->receives !== [],
            ));
        $packages = TopupPackage::query()->with([
            'game:id,name,package_mode,provider_service_code',
            'globalTopupPackage.provider',
            'globalTopupPackage.gameSettings',
        ])
            ->where('status', 'active')
            ->orderBy('game_id')->orderBy('denomination')->get();

        return $this->tenantContext->run($tenant, function () use ($tenant, $program, $rates, $globalRates, $globalCatalog, $packages): array {
            $this->pricingService->apply($packages);
            $linkedGlobalPackages = $packages
                ->filter(fn (TopupPackage $package): bool => $package->package_source === 'global' && $package->global_topup_package_id !== null)
                ->groupBy('global_topup_package_id');

            return [
                'site' => ['id' => $tenant->id, 'name' => $tenant->name, 'is_main' => $tenant->is_main],
                'program' => [
                    'is_enabled' => $program->is_enabled,
                    'minimum_withdrawal' => (int) $program->minimum_withdrawal,
                    'holding_days' => 7,
                    'minimum_conversion' => AffiliateWalletService::MINIMUM_CONVERSION,
                ],
                'global_rates' => $globalCatalog->map(function (GlobalTopupPackage $globalPackage) use ($tenant, $globalRates, $linkedGlobalPackages): array {
                    $mappedPackages = $linkedGlobalPackages->get($globalPackage->id, collect());
                    $rate = $globalRates->get($globalPackage->id);
                    $games = $globalPackage->gameSettings
                        ->filter(fn ($setting): bool => $setting->game?->package_mode === 'global'
                            && $setting->game?->status === 'active'
                            && is_array($setting->receives)
                            && $setting->receives !== [])
                        ->pluck('game.name')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                    $minimumMargin = $mappedPackages->isNotEmpty()
                        ? (int) $mappedPackages->map(fn (TopupPackage $package): int => $this->packageMargin($tenant, $package))->min()
                        : ($tenant->is_main ? max(0, $globalPackage->price - $globalPackage->provider_price) : 0);

                    return [
                        'global_package_id' => $globalPackage->id,
                        'package' => $globalPackage->name,
                        'denomination' => $globalPackage->denomination,
                        'games' => $games,
                        'minimum_margin' => $minimumMargin,
                        'commission_type' => $rate?->commission_type ?? AffiliatePackageRate::TYPE_FIXED,
                        'fixed_amount' => $rate?->fixed_amount ?? 0,
                        'percentage' => $rate?->percentage_basis_points === null ? 0 : $rate->percentage_basis_points / 100,
                        'is_active' => $rate?->is_active ?? false,
                    ];
                })->values()->all(),
                'rates' => $packages->map(function (TopupPackage $package) use ($tenant, $rates, $globalRates): array {
                    $rate = $rates->get($package->id);
                    $globalRate = $package->package_source === 'global'
                        ? $globalRates->get((int) $package->global_topup_package_id)
                        : null;
                    $effectiveRate = $rate?->is_active
                        ? $rate
                        : ($rate === null && $globalRate?->is_active ? $globalRate : null);
                    $sellingPrice = (int) $package->selling_price;
                    $mode = $rate instanceof AffiliatePackageRate
                        ? ($rate->is_active ? 'override' : 'disabled')
                        : ($package->package_source === 'global' ? 'global' : 'none');

                    return [
                        'package_id' => $package->id,
                        'global_package_id' => $package->global_topup_package_id,
                        'is_global' => $package->package_source === 'global',
                        'mode' => $mode,
                        'effective_source' => $effectiveRate instanceof AffiliatePackageRate
                            ? 'package'
                            : ($effectiveRate instanceof AffiliateGlobalPackageRate ? 'global' : null),
                        'game' => $package->game?->name,
                        'package' => $package->name,
                        'denomination' => (int) $package->denomination,
                        'selling_price' => $sellingPrice,
                        'margin' => $this->packageMargin($tenant, $package),
                        'commission_type' => $effectiveRate?->commission_type ?? AffiliatePackageRate::TYPE_FIXED,
                        'fixed_amount' => $effectiveRate?->fixed_amount ?? 0,
                        'percentage' => $effectiveRate?->percentage_basis_points === null ? 0 : $effectiveRate->percentage_basis_points / 100,
                        'is_active' => $effectiveRate?->is_active ?? false,
                    ];
                })->values()->all(),
                'sites' => $this->sites(),
            ];
        });
    }

    /** @param array<string, mixed> $payload */
    public function updateProgram(array $payload, User $admin, Request $request): AffiliateProgram
    {
        $tenantId = $this->resolveTenantId($payload['site_id'] ?? null);

        return DB::transaction(function () use ($payload, $tenantId, $admin, $request): AffiliateProgram {
            $program = AffiliateProgram::query()->withoutGlobalScope(TenantScope::class)
                ->firstOrNew(['tenant_id' => $tenantId]);
            $old = $program->exists ? $program->getAttributes() : [];
            $program->fill([
                'is_enabled' => (bool) $payload['is_enabled'],
                'minimum_withdrawal' => (int) $payload['minimum_withdrawal'],
            ])->save();
            $this->audit($tenantId, $admin, 'affiliate_program_updated', $program, $old, $program->getAttributes(), $request);

            return $program->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateRate(TopupPackage $package, array $payload, User $admin, Request $request): AffiliatePackageRate
    {
        $tenantId = $this->resolveTenantId($payload['site_id'] ?? null);
        $percentageBasisPoints = $payload['commission_type'] === AffiliatePackageRate::TYPE_PERCENTAGE
            ? (int) round((float) $payload['percentage'] * 100)
            : null;
        $fixedAmount = $payload['commission_type'] === AffiliatePackageRate::TYPE_FIXED ? (int) $payload['fixed_amount'] : null;
        $this->assertWithinMargin($tenantId, $package, $payload['commission_type'], $fixedAmount, $percentageBasisPoints, (bool) $payload['is_active']);

        return DB::transaction(function () use ($tenantId, $package, $payload, $fixedAmount, $percentageBasisPoints, $admin, $request): AffiliatePackageRate {
            $rate = AffiliatePackageRate::query()->withoutGlobalScope(TenantScope::class)
                ->firstOrNew(['tenant_id' => $tenantId, 'topup_package_id' => $package->id]);
            $old = $rate->exists ? $rate->getAttributes() : [];
            $rate->fill([
                'commission_type' => $payload['commission_type'],
                'fixed_amount' => $fixedAmount,
                'percentage_basis_points' => $percentageBasisPoints,
                'is_active' => (bool) $payload['is_active'],
            ])->save();
            $this->audit($tenantId, $admin, 'affiliate_package_rate_updated', $rate, $old, $rate->getAttributes(), $request);

            return $rate->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateGlobalRate(GlobalTopupPackage $globalPackage, array $payload, User $admin, Request $request): AffiliateGlobalPackageRate
    {
        $tenantId = $this->resolveTenantId($payload['site_id'] ?? null);
        $percentageBasisPoints = $payload['commission_type'] === AffiliatePackageRate::TYPE_PERCENTAGE
            ? (int) round((float) $payload['percentage'] * 100)
            : null;
        $fixedAmount = $payload['commission_type'] === AffiliatePackageRate::TYPE_FIXED ? (int) $payload['fixed_amount'] : null;
        $this->assertGlobalWithinMargin($tenantId, $globalPackage, $payload['commission_type'], $fixedAmount, $percentageBasisPoints, (bool) $payload['is_active']);

        return DB::transaction(function () use ($tenantId, $globalPackage, $payload, $fixedAmount, $percentageBasisPoints, $admin, $request): AffiliateGlobalPackageRate {
            $rate = AffiliateGlobalPackageRate::query()->withoutGlobalScope(TenantScope::class)
                ->firstOrNew(['tenant_id' => $tenantId, 'global_topup_package_id' => $globalPackage->id]);
            $old = $rate->exists ? $rate->getAttributes() : [];
            $rate->fill([
                'commission_type' => $payload['commission_type'],
                'fixed_amount' => $fixedAmount,
                'percentage_basis_points' => $percentageBasisPoints,
                'is_active' => (bool) $payload['is_active'],
            ])->save();
            $this->audit($tenantId, $admin, 'affiliate_global_package_rate_updated', $rate, $old, $rate->getAttributes(), $request);

            return $rate->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function resetRate(TopupPackage $package, array $payload, User $admin, Request $request): void
    {
        $tenantId = $this->resolveTenantId($payload['site_id'] ?? null);

        DB::transaction(function () use ($tenantId, $package, $admin, $request): void {
            $rate = AffiliatePackageRate::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenantId)
                ->where('topup_package_id', $package->id)
                ->first();

            if (! $rate instanceof AffiliatePackageRate) {
                return;
            }

            $old = $rate->getAttributes();
            $this->audit($tenantId, $admin, 'affiliate_package_rate_reset', $rate, $old, [], $request);
            $rate->delete();
        }, 3);
    }

    /** @param array<string, mixed> $filters */
    public function partners(array $filters): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['site_id'] ?? null, true);
        $search = trim((string) ($filters['search'] ?? ''));
        $profiles = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
            ->select(['id', 'tenant_id', 'user_id', 'status', 'bank_name', 'created_at'])
            ->with(['user' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)
                ->select(['id', 'tenant_id', 'username', 'email', 'full_name', 'referral_code', 'status'])])
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $userQuery) => $userQuery
                ->withoutGlobalScope(TenantScope::class)
                ->where(fn (Builder $nested) => $nested->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%"))))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
        $userIds = $profiles->getCollection()->pluck('user_id')->all();
        $commissionStats = AffiliateCommission::query()->withoutGlobalScope(TenantScope::class)
            ->selectRaw('referrer_id, COUNT(*) as orders_count, SUM(CASE WHEN status = ? OR (status = ? AND earned_at IS NOT NULL) THEN base_amount ELSE 0 END) as revenue, SUM(CASE WHEN status = ? AND earned_at IS NOT NULL THEN amount ELSE 0 END) as pending, SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as available', ['available', 'pending', 'pending', 'available'])
            ->whereIn('referrer_id', $userIds)->groupBy('referrer_id')->get()->keyBy('referrer_id');
        $referralCounts = User::query()->withoutGlobalScope(TenantScope::class)
            ->selectRaw('referred_by, COUNT(*) as aggregate')->whereIn('referred_by', $userIds)
            ->groupBy('referred_by')->pluck('aggregate', 'referred_by');
        $wallets = Wallet::query()->withoutGlobalScope(TenantScope::class)
            ->whereIn('user_id', $userIds)->where('type', Wallet::TYPE_AFFILIATE)->get()->keyBy('user_id');

        $profiles->setCollection($profiles->getCollection()->map(function (AffiliateProfile $profile) use ($commissionStats, $referralCounts, $wallets): array {
            $stats = $commissionStats->get($profile->user_id);
            $wallet = $wallets->get($profile->user_id);

            return [
                'id' => $profile->id, 'tenant_id' => $profile->tenant_id, 'status' => $profile->status,
                'user' => $profile->user, 'referrals_count' => (int) ($referralCounts[$profile->user_id] ?? 0),
                'orders_count' => (int) ($stats?->orders_count ?? 0), 'revenue' => (int) ($stats?->revenue ?? 0),
                'pending' => (int) ($stats?->pending ?? 0), 'available' => (int) ($stats?->available ?? 0),
                'wallet_balance' => (int) ($wallet?->balance ?? 0), 'hold_balance' => (int) ($wallet?->hold_balance ?? 0),
                'bank_name' => $profile->bank_name, 'created_at' => $profile->created_at?->toISOString(),
            ];
        }));

        return $profiles;
    }

    /** @param array<string, mixed> $filters */
    public function commissions(array $filters): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['site_id'] ?? null, true);
        $search = trim((string) ($filters['search'] ?? ''));

        return AffiliateCommission::query()->withoutGlobalScope(TenantScope::class)
            ->select(['id', 'tenant_id', 'order_id', 'referrer_id', 'referred_user_id', 'topup_package_id', 'commission_type', 'rate_value', 'base_amount', 'quantity', 'amount', 'holding_days', 'status', 'is_flagged', 'hold_reason', 'earned_at', 'available_at', 'reversed_at', 'created_at'])
            ->with([
                'order' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'tenant_id', 'code', 'payment_status', 'order_status']),
                'referrer' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'username', 'email']),
                'referredUser' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'username', 'email']),
                'package:id,name',
            ])
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->when(($filters['status'] ?? null) === 'flagged', fn (Builder $query) => $query->where('is_flagged', true))
            ->when(filled($filters['status'] ?? null) && $filters['status'] !== 'flagged', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->whereHas('order', fn (Builder $orderQuery) => $orderQuery->withoutGlobalScope(TenantScope::class)->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('referrer', fn (Builder $userQuery) => $userQuery->withoutGlobalScope(TenantScope::class)->where(fn (Builder $user) => $user
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")))
                    ->orWhereHas('referredUser', fn (Builder $userQuery) => $userQuery->withoutGlobalScope(TenantScope::class)->where(fn (Builder $user) => $user
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")))
                    ->orWhereHas('package', fn (Builder $packageQuery) => $packageQuery->where('name', 'like', "%{$search}%"));
            }))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id')->paginate((int) ($filters['per_page'] ?? 20));
    }

    /** @param array<string, mixed> $filters */
    public function withdrawals(array $filters): LengthAwarePaginator
    {
        $tenantId = $this->resolveTenantId($filters['site_id'] ?? null, true);
        $search = trim((string) ($filters['search'] ?? ''));

        return AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)
            ->select(['id', 'tenant_id', 'user_id', 'admin_id', 'amount', 'wallet_type', 'status', 'bank_name', 'bank_account_name', 'bank_account_number', 'bank_transaction_reference', 'admin_note', 'approved_at', 'paid_at', 'rejected_at', 'created_at'])
            ->with(['user' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'username', 'email'])])
            ->when($tenantId !== null, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $nested) use ($search): void {
                $nested->where('bank_transaction_reference', 'like', "%{$search}%")
                    ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->withoutGlobalScope(TenantScope::class)->where(fn (Builder $user) => $user
                        ->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")));
            }))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['date_to']))
            ->latest('id')->paginate((int) ($filters['per_page'] ?? 20))
            ->through(fn (AffiliateWithdrawal $withdrawal): array => $this->serializeWithdrawal($withdrawal));
    }

    /** @return array<string, mixed> */
    public function withdrawal(int $withdrawalId): array
    {
        $withdrawal = AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)
            ->with(['user' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)->select(['id', 'username', 'email'])])
            ->findOrFail($withdrawalId);
        $this->assertAdminTenant($withdrawal->tenant_id);

        return [
            ...$this->serializeWithdrawal($withdrawal),
            'bank_account_number' => $withdrawal->bank_account_number,
        ];
    }

    /** @param array<string, mixed> $payload */
    public function updateProfile(int $profileId, array $payload, User $admin, Request $request): AffiliateProfile
    {
        return DB::transaction(function () use ($profileId, $payload, $admin, $request): AffiliateProfile {
            $profile = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->findOrFail($profileId);
            $this->assertAdminTenant($profile->tenant_id);
            $old = $profile->only(['status', 'admin_note']);
            $profile->fill(['status' => $payload['status'], 'admin_note' => $payload['admin_note'] ?? null])->save();
            $this->audit($profile->tenant_id, $admin, 'affiliate_profile_status_updated', $profile, $old, $profile->only(['status', 'admin_note']), $request);

            return $profile->refresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function assignOrder(int $profileId, string $orderCode, User $admin, Request $request): array
    {
        return DB::transaction(function () use ($profileId, $orderCode, $admin, $request): array {
            $profile = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
                ->with(['user' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)])
                ->lockForUpdate()
                ->findOrFail($profileId);
            $this->assertAdminTenant($profile->tenant_id);

            if ($profile->status !== 'active' || ! $profile->user instanceof User || $profile->user->status !== 'active') {
                throw new ApiException('Cộng tác viên phải đang hoạt động để nhận đơn.', 422);
            }

            $order = Order::query()->withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $profile->tenant_id)
                ->where('code', $orderCode)
                ->lockForUpdate()
                ->first();

            if (! $order instanceof Order) {
                throw new ApiException('Không tìm thấy mã đơn topup trên website của cộng tác viên.', 404);
            }

            if ($order->affiliate_referrer_id !== null || $order->affiliateCommission()->withoutGlobalScope(TenantScope::class)->exists()) {
                throw new ApiException('Mã đơn này đã có cộng tác viên nhận.', 422);
            }

            if ($order->user_id === $profile->user_id || mb_strtolower(trim((string) $order->email)) === mb_strtolower(trim((string) $profile->user->email))) {
                throw new ApiException('Không thể gắn đơn của chính cộng tác viên.', 422);
            }

            $old = $order->only(['affiliate_referrer_id', 'affiliate_attribution_source', 'affiliate_referral_code', 'affiliate_attributed_at']);
            $order->forceFill([
                'affiliate_referrer_id' => $profile->user_id,
                'affiliate_attribution_source' => Order::AFFILIATE_SOURCE_ADMIN,
                'affiliate_referral_code' => $profile->user->referral_code,
                'affiliate_attributed_at' => now(),
            ])->save();

            $tenant = Tenant::query()->findOrFail($profile->tenant_id);
            $commission = $this->tenantContext->run($tenant, function () use ($order) {
                $commission = $this->commissionService->snapshot($order->refresh());
                $this->commissionService->markOrderCompleted($order->refresh());

                return $commission?->refresh();
            });

            $this->audit(
                $profile->tenant_id,
                $admin,
                'affiliate_order_manually_assigned',
                $order,
                $old,
                $order->only(['affiliate_referrer_id', 'affiliate_attribution_source', 'affiliate_referral_code', 'affiliate_attributed_at']),
                $request,
            );

            return [
                'order_code' => $order->code,
                'partner' => $profile->user->only(['id', 'username', 'email', 'referral_code']),
                'commission_id' => $commission?->id,
            ];
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateCommission(int $commissionId, array $payload, User $admin, Request $request): AffiliateCommission
    {
        return DB::transaction(function () use ($commissionId, $payload, $admin, $request): AffiliateCommission {
            $commission = AffiliateCommission::query()->withoutGlobalScope(TenantScope::class)
                ->lockForUpdate()->findOrFail($commissionId);
            $this->assertAdminTenant($commission->tenant_id);

            if ($commission->status !== AffiliateCommission::STATUS_PENDING) {
                throw new ApiException('Chỉ hoa hồng đang chờ mới có thể tạm giữ để kiểm tra.', 422);
            }

            $old = $commission->only(['is_flagged', 'hold_reason']);
            $isFlagged = $payload['action'] === 'flag';
            $commission->forceFill([
                'is_flagged' => $isFlagged,
                'hold_reason' => $isFlagged ? $payload['hold_reason'] : null,
            ])->save();
            $this->audit(
                $commission->tenant_id,
                $admin,
                'affiliate_commission_'.$payload['action'],
                $commission,
                $old,
                $commission->only(['is_flagged', 'hold_reason']),
                $request,
            );

            return $commission->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function updateWithdrawal(int $withdrawalId, array $payload, User $admin, Request $request): AffiliateWithdrawal
    {
        return DB::transaction(function () use ($withdrawalId, $payload, $admin, $request): AffiliateWithdrawal {
            $withdrawal = AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)->lockForUpdate()->findOrFail($withdrawalId);
            $this->assertAdminTenant($withdrawal->tenant_id);
            $old = $withdrawal->only(['status', 'bank_transaction_reference', 'admin_note']);

            match ($payload['action']) {
                'approve' => $this->approveWithdrawal($withdrawal, $admin, $payload),
                'reject' => $this->rejectWithdrawal($withdrawal, $admin, $payload),
                'mark_paid' => $this->payWithdrawal($withdrawal, $admin, $payload),
            };
            $this->audit($withdrawal->tenant_id, $admin, 'affiliate_withdrawal_'.$payload['action'], $withdrawal, $old, $withdrawal->only(['status', 'bank_transaction_reference', 'admin_note']), $request);

            return $withdrawal->refresh();
        }, 3);
    }

    /** @return array<int, array<string, mixed>> */
    private function siteOverview(): array
    {
        $sites = Tenant::query()->where('status', 'active')->orderByDesc('is_main')->orderBy('name')->get(['id', 'name']);
        $siteIds = $sites->pluck('id');
        $programs = AffiliateProgram::query()->withoutGlobalScope(TenantScope::class)
            ->whereIn('tenant_id', $siteIds)->pluck('is_enabled', 'tenant_id');
        $partnerStats = AffiliateProfile::query()->withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, COUNT(*) as partners_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_partners', ['active'])
            ->whereIn('tenant_id', $siteIds)->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $commissionStats = AffiliateCommission::query()->withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, COUNT(*) as commissions_count, SUM(CASE WHEN status = ? OR (status = ? AND earned_at IS NOT NULL) THEN base_amount ELSE 0 END) as revenue, SUM(CASE WHEN status = ? OR (status = ? AND earned_at IS NOT NULL) THEN amount ELSE 0 END) as commission_cost', ['available', 'pending', 'available', 'pending'])
            ->whereIn('tenant_id', $siteIds)->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $withdrawalStats = AffiliateWithdrawal::query()->withoutGlobalScope(TenantScope::class)
            ->selectRaw('tenant_id, SUM(CASE WHEN status IN (?, ?) THEN amount ELSE 0 END) as pending_withdrawal', ['requested', 'approved'])
            ->whereIn('tenant_id', $siteIds)->groupBy('tenant_id')->get()->keyBy('tenant_id');

        return $sites->map(function (Tenant $site) use ($programs, $partnerStats, $commissionStats, $withdrawalStats): array {
            $partners = $partnerStats->get($site->id);
            $commissions = $commissionStats->get($site->id);
            $withdrawals = $withdrawalStats->get($site->id);

            return [
                'tenant_id' => $site->id,
                'site' => $site->name,
                'is_enabled' => (bool) ($programs[$site->id] ?? false),
                'partners_count' => (int) ($partners?->partners_count ?? 0),
                'active_partners' => (int) ($partners?->active_partners ?? 0),
                'commissions_count' => (int) ($commissions?->commissions_count ?? 0),
                'revenue' => (int) ($commissions?->revenue ?? 0),
                'commission_cost' => (int) ($commissions?->commission_cost ?? 0),
                'pending_withdrawal' => (int) ($withdrawals?->pending_withdrawal ?? 0),
            ];
        })->all();
    }

    /** @return array<int, array{id:int,name:string}> */
    private function sites(): array
    {
        if (! Site::isMain()) {
            $site = Site::mySite();

            return $site ? [['id' => $site->id, 'name' => $site->name]] : [];
        }

        return Tenant::query()->where('status', 'active')->orderByDesc('is_main')->orderBy('name')
            ->get(['id', 'name'])->map->only(['id', 'name'])->all();
    }

    private function resolveTenantId(mixed $requestedTenantId, bool $allowAll = false): ?int
    {
        if (! Site::isMain()) {
            return (int) $this->tenantContext->idOrMain();
        }

        if ($allowAll && ! filled($requestedTenantId)) {
            return null;
        }

        return filled($requestedTenantId)
            ? (int) Tenant::query()->whereKey((int) $requestedTenantId)->value('id')
            : (int) $this->tenantContext->idOrMain();
    }

    private function assertAdminTenant(int $tenantId): void
    {
        abort_unless(Site::isMain() || $tenantId === (int) $this->tenantContext->idOrMain(), 403);
    }

    private function assertWithinMargin(int $tenantId, TopupPackage $package, string $type, ?int $fixedAmount, ?int $basisPoints, bool $active): void
    {
        if (! $active) {
            return;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $pricing = $this->tenantContext->run($tenant, fn (): array => $this->pricingService->resolve($package));
        $sellingPrice = (int) $pricing['final_price'];
        $margin = $tenant->is_main
            ? max(0, $sellingPrice - (int) ($pricing['provider_price'] ?? 0))
            : max(0, (int) $pricing['tenant_profit']);
        $commission = $type === AffiliatePackageRate::TYPE_PERCENTAGE
            ? intdiv($sellingPrice * (int) $basisPoints, 10000)
            : (int) $fixedAmount;

        if ($commission > $margin) {
            throw ValidationException::withMessages([
                'commission' => 'Hoa hồng dự kiến vượt quá lợi nhuận hiện tại của gói trên website này.',
            ]);
        }
    }

    private function assertGlobalWithinMargin(
        int $tenantId,
        GlobalTopupPackage $globalPackage,
        string $type,
        ?int $fixedAmount,
        ?int $basisPoints,
        bool $active,
    ): void {
        if (! $active) {
            return;
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        $packages = TopupPackage::query()
            ->with([
                'game:id,package_mode,provider_service_code',
                'globalTopupPackage.provider',
                'globalTopupPackage.gameSettings',
            ])
            ->where('global_topup_package_id', $globalPackage->id)
            ->where('status', 'active')
            ->whereHas('game', fn (Builder $query) => $query->where('package_mode', 'global'))
            ->get();

        $invalidPackages = $this->tenantContext->run($tenant, function () use ($tenant, $packages, $type, $fixedAmount, $basisPoints): array {
            return $packages->filter(function (TopupPackage $package) use ($tenant, $type, $fixedAmount, $basisPoints): bool {
                $pricing = $this->pricingService->resolve($package);
                $sellingPrice = (int) $pricing['final_price'];
                $margin = $tenant->is_main
                    ? max(0, $sellingPrice - (int) ($pricing['provider_price'] ?? 0))
                    : max(0, (int) $pricing['tenant_profit']);
                $commission = $type === AffiliatePackageRate::TYPE_PERCENTAGE
                    ? intdiv($sellingPrice * (int) $basisPoints, 10000)
                    : (int) $fixedAmount;

                return $commission > $margin;
            })->pluck('name')->take(3)->all();
        });

        if ($invalidPackages !== []) {
            throw ValidationException::withMessages([
                'commission' => 'Hoa hồng Global vượt lợi nhuận của một số gói đang sử dụng: '.implode(', ', $invalidPackages).'.',
            ]);
        }
    }

    private function packageMargin(Tenant $tenant, TopupPackage $package): int
    {
        return $tenant->is_main
            ? max(0, (int) $package->selling_price - (int) ($package->provider_price ?? 0))
            : max(0, (int) $package->tenant_profit);
    }

    /** @param array<string, mixed> $payload */
    private function approveWithdrawal(AffiliateWithdrawal $withdrawal, User $admin, array $payload): void
    {
        if ($withdrawal->status !== AffiliateWithdrawal::STATUS_REQUESTED) {
            throw new ApiException('Chỉ yêu cầu mới được phép duyệt.', 422);
        }

        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_APPROVED, 'admin_id' => $admin->id,
            'admin_note' => $payload['admin_note'] ?? null, 'approved_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $payload */
    private function rejectWithdrawal(AffiliateWithdrawal $withdrawal, User $admin, array $payload): void
    {
        if (! in_array($withdrawal->status, [AffiliateWithdrawal::STATUS_REQUESTED, AffiliateWithdrawal::STATUS_APPROVED], true)) {
            throw new ApiException('Yêu cầu này không thể bị từ chối ở trạng thái hiện tại.', 422);
        }

        $this->walletService->releaseWithdrawal($withdrawal);
        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_REJECTED, 'admin_id' => $admin->id,
            'admin_note' => $payload['admin_note'] ?? null, 'rejected_at' => now(),
        ])->save();
    }

    /** @param array<string, mixed> $payload */
    private function payWithdrawal(AffiliateWithdrawal $withdrawal, User $admin, array $payload): void
    {
        if ($withdrawal->status !== AffiliateWithdrawal::STATUS_APPROVED) {
            throw new ApiException('Yêu cầu phải được duyệt trước khi xác nhận thanh toán.', 422);
        }

        $this->walletService->settleWithdrawal($withdrawal);
        $withdrawal->forceFill([
            'status' => AffiliateWithdrawal::STATUS_PAID, 'admin_id' => $admin->id,
            'admin_note' => $payload['admin_note'] ?? null,
            'bank_transaction_reference' => $payload['bank_transaction_reference'], 'paid_at' => now(),
        ])->save();
    }

    /** @return array<string, mixed> */
    private function serializeWithdrawal(AffiliateWithdrawal $withdrawal): array
    {
        $accountNumber = (string) $withdrawal->bank_account_number;

        return [
            'id' => $withdrawal->id, 'tenant_id' => $withdrawal->tenant_id, 'user' => $withdrawal->user,
            'amount' => $withdrawal->amount, 'wallet_type' => $withdrawal->wallet_type,
            'status' => $withdrawal->status, 'bank_name' => $withdrawal->bank_name,
            'bank_account_name' => $withdrawal->bank_account_name,
            'bank_account_number_masked' => str_repeat('*', max(0, mb_strlen($accountNumber) - 4)).mb_substr($accountNumber, -4),
            'bank_transaction_reference' => $withdrawal->bank_transaction_reference,
            'admin_note' => $withdrawal->admin_note, 'created_at' => $withdrawal->created_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAnnouncement(AffiliateAnnouncement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'tenant_id' => $announcement->tenant_id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'content_html' => $this->contentRenderer->renderNodes($announcement->content ?? [])->toHtml(),
            'is_pinned' => $announcement->is_pinned,
            'is_published' => $announcement->is_published,
            'published_at' => $announcement->published_at?->toISOString(),
            'updated_at' => $announcement->updated_at?->toISOString(),
            'admin' => $announcement->admin?->only(['id', 'username', 'full_name']),
        ];
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function audit(int $tenantId, User $admin, string $action, object $subject, array $old, array $new, Request $request): void
    {
        AdminAuditLog::query()->withoutGlobalScope(TenantScope::class)->create([
            'tenant_id' => $tenantId, 'admin_id' => $admin->id, 'action' => $action,
            'subject_type' => $subject::class, 'subject_id' => $subject->id,
            'old_values' => $old, 'new_values' => $new,
            'ip' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
    }
}
