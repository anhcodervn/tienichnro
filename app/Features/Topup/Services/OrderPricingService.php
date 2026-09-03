<?php

namespace App\Features\Topup\Services;

use App\Models\GameServer;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    public function __construct(
        private readonly TopupPackagePricingService $topupPackagePricingService,
        private readonly GameRewardService $gameRewardService,
    ) {}

    /**
     * @param  array<int, int>  $recipientQuantities
     * @return array<string, mixed>
     */
    public function quote(
        int $gameId,
        int $serverId,
        int $packageId,
        int $quantity,
        bool $lock = false,
        string $quantityField = 'quantity',
        array $recipientQuantities = [],
        ?User $user = null,
    ): array {
        $query = TopupPackage::query()->with([
            'game:id,name,short_name,reward_label,provider_service_code,status,package_mode',
            'globalTopupPackage.provider',
            'server:id,game_id,name,code,status',
        ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $package = $query->find($packageId);

        if (! $package instanceof TopupPackage || $package->status !== 'active' || $package->game?->status !== 'active') {
            throw ValidationException::withMessages(['package_id' => 'Gói nạp không tồn tại hoặc đang tạm tắt.']);
        }

        if ($package->game_id !== $gameId) {
            throw ValidationException::withMessages(['package_id' => 'Gói nạp không thuộc trò chơi đã chọn.']);
        }

        $this->gameRewardService->applyToPackage($package);

        $serverQuery = GameServer::query()
            ->whereKey($serverId)
            ->where('game_id', $gameId)
            ->where('status', 'active');

        if ($lock) {
            $serverQuery->lockForUpdate();
        }

        $server = $serverQuery->first();

        if (! $server instanceof GameServer) {
            throw ValidationException::withMessages(['server_id' => 'Máy chủ không tồn tại hoặc đang tạm tắt.']);
        }

        $price = $this->topupPackagePricingService->resolve($package, $user);
        $quantitiesToValidate = $recipientQuantities !== [] ? $recipientQuantities : [$quantity];
        $hasInvalidQuantity = collect($quantitiesToValidate)->contains(
            fn (mixed $recipientQuantity): bool => (int) $recipientQuantity < $package->min_quantity
                || ($package->max_quantity !== null && (int) $recipientQuantity > $package->max_quantity),
        );

        if ($hasInvalidQuantity) {
            throw ValidationException::withMessages([$quantityField => 'Số lượng không nằm trong giới hạn của gói nạp.']);
        }

        $retailPrice = $price['retail_price'];
        $sellingPrice = $price['final_price'];
        $tenantCostPrice = (int) $price['tenant_cost_price'];
        $unitPrice = $price['original_price'];
        $subtotal = $unitPrice * $quantity;
        $totalAmount = $sellingPrice * $quantity;
        $providerUnitCost = $price['provider_price'];
        $providerTotalCost = $providerUnitCost === null ? null : $providerUnitCost * $quantity;

        return [
            'package' => $package,
            'server' => $server,
            'unit_price' => $unitPrice,
            'sale_unit_price' => $sellingPrice,
            'retail_unit_price' => $retailPrice,
            'subtotal' => $subtotal,
            'discount_amount' => $subtotal - $totalAmount,
            'total_amount' => $totalAmount,
            'provider_unit_cost' => $providerUnitCost,
            'provider_total_cost' => $providerTotalCost,
            'gross_profit' => $providerTotalCost === null ? null : $totalAmount - $providerTotalCost,
            'tenant_cost_unit_price' => $tenantCostPrice,
            'tenant_cost_total' => $tenantCostPrice * $quantity,
            'tenant_profit' => ((int) $price['tenant_profit']) * $quantity,
            'tenant_pricing_mode' => $price['tenant_pricing_mode'] ?? 'base_price',
            'package_source' => $price['package_source'],
            'global_topup_package_id' => $price['global_topup_package_id'],
            'global_topup_package_name' => $price['global_topup_package_name'],
        ];
    }
}
