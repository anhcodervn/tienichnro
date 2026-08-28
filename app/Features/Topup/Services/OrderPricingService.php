<?php

namespace App\Features\Topup\Services;

use App\Models\GameServer;
use App\Models\TopupPackage;
use Illuminate\Validation\ValidationException;

class OrderPricingService
{
    /**
     * @param  array<int, int>  $recipientQuantities
     * @return array{package:TopupPackage,server:GameServer,unit_price:int,sale_unit_price:int,subtotal:int,discount_amount:int,total_amount:int,provider_unit_cost:int|null,provider_total_cost:int|null,gross_profit:int|null}
     */
    public function quote(
        int $gameId,
        int $serverId,
        int $packageId,
        int $quantity,
        bool $lock = false,
        string $quantityField = 'quantity',
        array $recipientQuantities = [],
    ): array {
        $query = TopupPackage::query()->with(['game:id,name,status', 'server:id,game_id,name,status']);

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

        $sellingPrice = (int) $package->price;
        $unitPrice = max($sellingPrice, (int) ($package->original_price ?? $sellingPrice));
        $subtotal = $unitPrice * $quantity;
        $totalAmount = $sellingPrice * $quantity;
        $providerUnitCost = $package->provider_price === null ? null : (int) $package->provider_price;
        $providerTotalCost = $providerUnitCost === null ? null : $providerUnitCost * $quantity;

        return [
            'package' => $package,
            'server' => $server,
            'unit_price' => $unitPrice,
            'sale_unit_price' => $sellingPrice,
            'subtotal' => $subtotal,
            'discount_amount' => $subtotal - $totalAmount,
            'total_amount' => $totalAmount,
            'provider_unit_cost' => $providerUnitCost,
            'provider_total_cost' => $providerTotalCost,
            'gross_profit' => $providerTotalCost === null ? null : $totalAmount - $providerTotalCost,
        ];
    }
}
