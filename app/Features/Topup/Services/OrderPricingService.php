<?php

namespace App\Features\Topup\Services;

use App\Features\MemberLevel\Services\MemberLevelPriceService;
use App\Models\GameServer;
use App\Models\TopupPackage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    public function __construct(private readonly MemberLevelPriceService $memberLevelPriceService) {}

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
            'game:id,name,status,package_mode',
            'globalTopupPackage:id,name,denomination,price,original_price,status',
            'server:id,game_id,name,status',
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

        if ($package->game_server_id !== null && $package->game_server_id !== $serverId) {
            throw ValidationException::withMessages(['server_id' => 'Gói nạp không áp dụng cho máy chủ đã chọn.']);
        }

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

        $quantitiesToValidate = $recipientQuantities !== [] ? $recipientQuantities : [$quantity];
        $hasInvalidQuantity = collect($quantitiesToValidate)->contains(
            fn (mixed $recipientQuantity): bool => (int) $recipientQuantity < $package->min_quantity
                || ($package->max_quantity !== null && (int) $recipientQuantity > $package->max_quantity),
        );

        if ($hasInvalidQuantity) {
            throw ValidationException::withMessages([$quantityField => 'Số lượng không nằm trong giới hạn của gói nạp.']);
        }

        $memberPrice = $this->memberLevelPriceService->resolve($package, $user);
        $retailPrice = $memberPrice['retail_price'];
        $sellingPrice = $memberPrice['final_price'];
        $unitPrice = $memberPrice['original_price'];
        $subtotal = $unitPrice * $quantity;
        $totalAmount = $sellingPrice * $quantity;
        $providerUnitCost = $package->provider_price === null ? null : (int) $package->provider_price;
        $providerTotalCost = $providerUnitCost === null ? null : $providerUnitCost * $quantity;

        return [
            'package' => $package,
            'server' => $server,
            'unit_price' => $unitPrice,
            'sale_unit_price' => $sellingPrice,
            'retail_unit_price' => $retailPrice,
            'subtotal' => $subtotal,
            'discount_amount' => $subtotal - $totalAmount,
            'member_level_discount_amount' => $memberPrice['discount_amount'] * $quantity,
            'total_amount' => $totalAmount,
            'provider_unit_cost' => $providerUnitCost,
            'provider_total_cost' => $providerTotalCost,
            'gross_profit' => $providerTotalCost === null ? null : $totalAmount - $providerTotalCost,
            'member_level_id' => $memberPrice['level_id'],
            'member_level_name' => $memberPrice['level_name'],
            'member_level_pricing_mode' => $memberPrice['pricing_mode'],
            'member_level_discount_bps' => $memberPrice['discount_basis_points'],
            'package_source' => $memberPrice['package_source'],
            'global_topup_package_id' => $memberPrice['global_topup_package_id'],
            'global_topup_package_name' => $memberPrice['global_topup_package_name'],
        ];
    }
}
