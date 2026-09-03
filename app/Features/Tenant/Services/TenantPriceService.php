<?php

namespace App\Features\Tenant\Services;

use App\Models\Tenant;
use App\Models\TenantPackagePrice;
use App\Models\TopupPackage;
use Illuminate\Validation\ValidationException;

class TenantPriceService
{
    /** @var array<int, array<int, TenantPackagePrice|null>> */
    private array $rules = [];

    /** @param array<int, int> $packageIds */
    public function prime(Tenant $tenant, array $packageIds): void
    {
        $rules = TenantPackagePrice::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('topup_package_id', $packageIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('topup_package_id');

        foreach ($packageIds as $packageId) {
            $this->rules[$tenant->id][$packageId] = $rules->get($packageId);
        }
    }

    /** @return array{cost_price:int,selling_price:int,profit:int,pricing_mode:string} */
    public function resolve(Tenant $tenant, TopupPackage $package, int $costPrice): array
    {
        if ($tenant->is_main) {
            return $this->result($costPrice, $costPrice, 'base_price');
        }

        if (! array_key_exists($package->id, $this->rules[$tenant->id] ?? [])) {
            $this->prime($tenant, [$package->id]);
        }

        $rule = $this->rules[$tenant->id][$package->id];

        $sellingPrice = match ($rule?->pricing_mode) {
            TenantPackagePrice::MODE_FIXED => (int) ($rule->fixed_price ?? $costPrice),
            TenantPackagePrice::MODE_MARKUP_PERCENTAGE => $costPrice + intdiv($costPrice * (int) ($rule->markup_basis_points ?? 0), 10000),
            default => $costPrice + (int) ($rule?->markup_amount ?? 0),
        };

        if ($sellingPrice < $costPrice && ! $tenant->allow_below_cost) {
            throw ValidationException::withMessages([
                'package_id' => 'Giá bán của website đang thấp hơn giá vốn tài khoản NapCarot.',
            ]);
        }

        return $this->result($costPrice, max(0, $sellingPrice), $rule?->pricing_mode ?? 'cost');
    }

    /** @return array{cost_price:int,selling_price:int,profit:int,pricing_mode:string} */
    private function result(int $costPrice, int $sellingPrice, string $pricingMode): array
    {
        return [
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'profit' => $sellingPrice - $costPrice,
            'pricing_mode' => $pricingMode,
        ];
    }
}
