<?php

namespace App\Features\Admin\Topup\Services;

use App\Enums\TopupProviderType;
use App\Features\Topup\Contracts\TopupProviderCatalogInterface;
use App\Features\Topup\DTOs\TopupProviderProductDto;
use App\Features\Topup\Exceptions\TopupProviderConnectionException;
use App\Features\Topup\Services\GlobalTopupPackageSyncService;
use App\Features\Topup\Services\TopupProviderResolver;
use App\Models\GlobalTopupPackage;
use App\Models\TopupPackage;
use App\Models\TopupProvider;
use App\Models\TopupProviderPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProviderProductCatalogSyncService
{
    public function __construct(
        private readonly TopupProviderResolver $providerResolver,
        private readonly GlobalTopupPackageSyncService $globalPackageSyncService,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function refresh(): array
    {
        $results = [];
        $providers = TopupProvider::query()
            ->whereIn('type', [
                TopupProviderType::MerchantPartnerCard->value,
                TopupProviderType::AccNro->value,
            ])
            ->orderBy('id')
            ->get();

        foreach ($providers as $provider) {
            $startedAt = hrtime(true);

            try {
                $providerAdapter = $this->providerResolver->resolve($provider);

                if (! $providerAdapter instanceof TopupProviderCatalogInterface) {
                    throw new TopupProviderConnectionException('catalog_unsupported', 'Provider không hỗ trợ đồng bộ bảng giá.');
                }

                $products = $providerAdapter->products($provider);
                $counts = $this->syncProviderPrices($provider, $products);
                $latency = $this->latencyInMilliseconds($startedAt);
                $provider->update([
                    'price_sync_status' => 'success',
                    'price_synced_at' => now(),
                    'price_sync_error_code' => null,
                    'price_sync_error_message' => null,
                    'price_sync_latency_ms' => $latency,
                ]);
                $results[$provider->id] = ['status' => 'success', 'latency_ms' => $latency, ...$counts];
            } catch (Throwable $exception) {
                $diagnostic = TopupProviderConnectionException::fromThrowable($exception);
                $latency = $this->latencyInMilliseconds($startedAt);
                $provider->update([
                    'price_sync_status' => 'failed',
                    'price_synced_at' => now(),
                    'price_sync_error_code' => $diagnostic->errorCode,
                    'price_sync_error_message' => mb_substr($diagnostic->getMessage(), 0, 500),
                    'price_sync_latency_ms' => $latency,
                ]);
                $results[$provider->id] = [
                    'status' => 'failed',
                    'latency_ms' => $latency,
                    'error_code' => $diagnostic->errorCode,
                    'error_message' => $diagnostic->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * @param  Collection<int, TopupProviderProductDto>  $products
     * @return array{matched_packages:int,matched_global_packages:int,products:int}
     */
    private function syncProviderPrices(TopupProvider $provider, Collection $products): array
    {
        if ($products->isEmpty()) {
            throw new TopupProviderConnectionException('empty_catalog', 'Provider không trả về sản phẩm hợp lệ nào.');
        }

        $productIndex = $products
            ->groupBy(fn (TopupProviderProductDto $product): string => $this->productKey($product->serviceCode, $product->denomination))
            ->map(fn (Collection $matches): int => (int) $matches->min('price'));

        return DB::transaction(function () use ($provider, $productIndex, $products): array {
            $now = now();
            $matchedPackages = 0;
            $matchedGlobalPackages = 0;
            $customPackages = TopupPackage::query()
                ->with('game:id,provider_service_code')
                ->whereNull('global_topup_package_id')
                ->active()
                ->get(['id', 'game_id', 'provider_id', 'provider_price', 'denomination']);
            $globalPackages = GlobalTopupPackage::query()
                ->with(['packages' => fn ($query) => $query->active()->with('game:id,provider_service_code')])
                ->where('status', 'active')
                ->get(['id', 'provider_id', 'provider_service_codes', 'provider_price', 'denomination']);

            TopupProviderPrice::query()
                ->whereBelongsTo($provider, 'provider')
                ->where(function ($query) use ($customPackages, $globalPackages): void {
                    $query->whereIn('topup_package_id', $customPackages->modelKeys())
                        ->orWhereIn('global_topup_package_id', $globalPackages->modelKeys());
                })
                ->update(['available' => false, 'synced_at' => $now]);

            foreach ($customPackages as $package) {
                $serviceCode = trim((string) $package->game?->provider_service_code);
                $price = $productIndex->get($this->productKey($serviceCode, (int) $package->denomination));

                if (! is_int($price)) {
                    continue;
                }

                $package->providerPrices()->updateOrCreate(
                    ['topup_provider_id' => $provider->id],
                    ['price' => $price, 'available' => true, 'synced_at' => $now],
                );

                if ($package->provider_id === $provider->id) {
                    $package->update(['provider_price' => $price]);
                }

                $matchedPackages++;
            }

            foreach ($globalPackages as $package) {
                $serviceCodes = $this->globalServiceCodes($package);
                $prices = $serviceCodes
                    ->map(fn (string $serviceCode): mixed => $productIndex->get($this->productKey($serviceCode, (int) $package->denomination)));

                if ($serviceCodes->isEmpty() || $prices->contains(fn (mixed $price): bool => ! is_int($price)) || $prices->unique()->count() !== 1) {
                    continue;
                }

                $price = (int) $prices->first();
                $package->providerPrices()->updateOrCreate(
                    ['topup_provider_id' => $provider->id],
                    ['price' => $price, 'available' => true, 'synced_at' => $now],
                );

                if ($package->provider_id === $provider->id) {
                    $package->update(['provider_price' => $price]);
                    $this->globalPackageSyncService->sync($package->refresh());
                }

                $matchedGlobalPackages++;
            }

            return [
                'matched_packages' => $matchedPackages,
                'matched_global_packages' => $matchedGlobalPackages,
                'products' => $products->count(),
            ];
        }, 3);
    }

    /** @return Collection<int, string> */
    private function globalServiceCodes(GlobalTopupPackage $package): Collection
    {
        $configuredCodes = collect($package->provider_service_codes ?? [])->flatten();

        return $package->packages
            ->map(fn (TopupPackage $child): ?string => $child->game?->provider_service_code)
            ->concat($configuredCodes)
            ->map(fn (mixed $code): string => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();
    }

    private function productKey(string $serviceCode, int $denomination): string
    {
        return strtoupper(trim($serviceCode)).'|'.$denomination;
    }

    private function latencyInMilliseconds(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }
}
