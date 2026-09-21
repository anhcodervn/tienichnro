<?php

namespace App\Features\Admin\Topup\Services;

use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Models\AdminAuditLog;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\TopupProviderPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderPriceAdminService
{
    public function __construct(private readonly GlobalTopupPackageSyncService $globalPackageSyncService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array{packages: Collection<int, array<string, mixed>>, providers: Collection<int, TopupProvider>}
     */
    public function catalog(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $scope = (string) ($filters['scope'] ?? 'all');
        $providerId = isset($filters['provider_id']) ? (int) $filters['provider_id'] : null;
        $rows = collect();

        if ($scope !== 'global') {
            $rows = $rows->concat(TopupPackage::query()
                ->select(['id', 'game_id', 'provider_id', 'name', 'denomination', 'provider_price', 'price', 'original_price'])
                ->with(['game:id,name', 'provider:id,name,slug,type', 'providerPrices:id,topup_provider_id,topup_package_id,price'])
                ->whereNull('global_topup_package_id')
                ->active()
                ->when($providerId !== null, fn ($query) => $query->whereHas(
                    'providerPrices',
                    fn ($quotes) => $quotes->where('topup_provider_id', $providerId),
                ))
                ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhereHas('game', fn ($game) => $game->where('name', 'like', "%{$search}%"));
                }))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (TopupPackage $package): array => $this->row($package, 'package')));
        }

        if ($scope !== 'package') {
            $rows = $rows->concat(GlobalTopupPackage::query()
                ->select(['id', 'provider_id', 'name', 'denomination', 'provider_price', 'price', 'original_price'])
                ->with(['provider:id,name,slug,type', 'providerPrices:id,topup_provider_id,global_topup_package_id,price'])
                ->where('status', 'active')
                ->when($providerId !== null, fn ($query) => $query->whereHas(
                    'providerPrices',
                    fn ($quotes) => $quotes->where('topup_provider_id', $providerId),
                ))
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (GlobalTopupPackage $package): array => $this->row($package, 'global')));
        }

        return [
            'packages' => $rows->values(),
            'providers' => TopupProvider::query()->orderBy('name')->orderBy('id')->get([
                'id',
                'name',
                'slug',
                'type',
                'balance_status',
                'balance_checked_at',
                'balance_error_message',
            ]),
        ];
    }

    public function updateSalePrice(string $scope, int $id, int $price, User $admin, Request $request): array
    {
        return DB::transaction(function () use ($scope, $id, $price, $admin, $request): array {
            $package = $this->findPackage($scope, $id, true);
            $this->assertSalePrice($package, $price);
            $old = $package->getAttributes();
            $selectedQuote = $package->provider_id === null
                ? null
                : $package->providerPrices()->where('topup_provider_id', $package->provider_id)->first();
            $selectedProviderPrice = $selectedQuote?->price ?? (int) $package->provider_price;

            if ($package->provider_id !== null && $selectedProviderPrice > $price) {
                throw ValidationException::withMessages([
                    'price' => 'Giá bán không được thấp hơn giá vốn của provider đang kết nối.',
                ]);
            }

            $package->update([
                'provider_price' => $selectedQuote?->price ?? $package->provider_price,
                'price' => $price,
            ]);
            $this->syncGlobalPackage($package);
            $this->audit($admin, 'provider_sale_price_updated', $package, $old, $request);

            return $this->freshRow($package, $scope);
        }, 3);
    }

    public function updateQuote(
        string $scope,
        int $id,
        TopupProvider $provider,
        ?int $providerPrice,
        User $admin,
        Request $request,
    ): array {
        return DB::transaction(function () use ($scope, $id, $provider, $providerPrice, $admin, $request): array {
            $package = $this->findPackage($scope, $id, true);
            $old = $package->getAttributes();

            if ($providerPrice === null) {
                if ($package->provider_id === $provider->id) {
                    throw ValidationException::withMessages([
                        'provider_price' => 'Không thể xóa giá của provider đang kết nối.',
                    ]);
                }

                $package->providerPrices()->whereBelongsTo($provider, 'provider')->delete();
            } else {
                $package->providerPrices()->updateOrCreate(
                    ['topup_provider_id' => $provider->id],
                    ['price' => $providerPrice],
                );

                if ($package->provider_id === $provider->id) {
                    if ($providerPrice > (int) $package->price) {
                        throw ValidationException::withMessages([
                            'provider_price' => 'Giá provider đang kết nối không được lớn hơn giá bán.',
                        ]);
                    }

                    $package->update(['provider_price' => $providerPrice]);
                    $this->syncGlobalPackage($package);
                }
            }

            $this->audit($admin, 'provider_quote_updated', $package, $old, $request);

            return $this->freshRow($package, $scope);
        }, 3);
    }

    public function selectProvider(
        string $scope,
        int $id,
        TopupProvider $provider,
        int $providerPrice,
        int $salePrice,
        User $admin,
        Request $request,
    ): array {
        return DB::transaction(function () use ($scope, $id, $provider, $providerPrice, $salePrice, $admin, $request): array {
            $package = $this->findPackage($scope, $id, true);
            $this->assertSalePrice($package, $salePrice);
            $old = $package->getAttributes();

            $package->providerPrices()->updateOrCreate(
                ['topup_provider_id' => $provider->id],
                ['price' => $providerPrice],
            );
            $package->update([
                'provider_id' => $provider->id,
                'provider_price' => $providerPrice,
                'price' => $salePrice,
            ]);
            $this->syncGlobalPackage($package);
            $this->audit($admin, 'provider_selected', $package, $old, $request);

            return $this->freshRow($package, $scope);
        }, 3);
    }

    private function findPackage(string $scope, int $id, bool $lock): TopupPackage|GlobalTopupPackage
    {
        $query = $scope === 'global'
            ? GlobalTopupPackage::query()->where('status', 'active')
            : TopupPackage::query()->whereNull('global_topup_package_id')->active();

        if ($lock) {
            $query->lockForUpdate();
        }

        $package = $query->find($id);

        if (! $package instanceof TopupPackage && ! $package instanceof GlobalTopupPackage) {
            abort(404);
        }

        return $package;
    }

    private function assertSalePrice(TopupPackage|GlobalTopupPackage $package, int $price): void
    {
        if ($price > (int) $package->original_price) {
            throw ValidationException::withMessages([
                'price' => 'Giá bán không được lớn hơn giá gốc của gói.',
            ]);
        }
    }

    private function syncGlobalPackage(Model $package): void
    {
        if ($package instanceof GlobalTopupPackage) {
            $this->globalPackageSyncService->sync($package->refresh());
        }
    }

    private function audit(User $admin, string $action, Model $package, array $old, Request $request): void
    {
        AdminAuditLog::query()->create([
            'admin_id' => $admin->id,
            'action' => $action,
            'subject_type' => $package::class,
            'subject_id' => $package->getKey(),
            'old_values' => $old,
            'new_values' => $package->getAttributes(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /** @return array<string, mixed> */
    private function freshRow(TopupPackage|GlobalTopupPackage $package, string $scope): array
    {
        $relations = $package instanceof TopupPackage
            ? ['game:id,name', 'provider:id,name,slug,type', 'providerPrices']
            : ['provider:id,name,slug,type', 'providerPrices'];

        return $this->row($package->refresh()->load($relations), $scope);
    }

    /** @return array<string, mixed> */
    private function row(TopupPackage|GlobalTopupPackage $package, string $scope): array
    {
        $providerPrice = (int) $package->provider_price;
        $price = (int) $package->price;
        $quotes = $package->providerPrices
            ->mapWithKeys(fn (TopupProviderPrice $quote): array => [(string) $quote->topup_provider_id => $quote->price]);
        $bestPrice = $quotes->min();
        $bestProviderId = $bestPrice === null
            ? null
            : (int) $quotes->search(fn (int $quote): bool => $quote === $bestPrice);

        return [
            'id' => $package->id,
            'scope' => $scope,
            'game_name' => $package instanceof TopupPackage ? $package->game?->name : 'Global',
            'name' => $package->name,
            'denomination' => (int) $package->denomination,
            'provider_id' => $package->provider_id,
            'provider_name' => $package->provider?->name,
            'provider_price' => $providerPrice,
            'provider_prices' => $quotes,
            'best_provider_id' => $bestProviderId,
            'price' => $price,
            'original_price' => (int) $package->original_price,
            'profit' => $price - $providerPrice,
            'profit_percent' => $price > 0 ? round((($price - $providerPrice) * 100) / $price, 2) : 0,
        ];
    }
}
