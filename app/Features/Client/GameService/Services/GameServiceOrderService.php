<?php

namespace App\Features\Client\GameService\Services;

use App\Features\Topup\Services\OrderProfitCalculatorService;
use App\Features\Topup\Services\TaxConfigurationService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameServiceOrderService
{
    public function __construct(
        private readonly TaxConfigurationService $taxConfigurationService,
        private readonly OrderProfitCalculatorService $orderProfitCalculator,
    ) {}

    /** @param array<string, mixed> $payload */
    public function create(Game $game, GameService $service, array $payload, ?User $user): GameServiceOrder
    {
        return DB::transaction(function () use ($game, $service, $payload, $user): GameServiceOrder {
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);
            $lockedService = GameService::query()->lockForUpdate()->findOrFail($service->id);

            if ($lockedGame->status !== 'active' || ! $lockedGame->game_services_enabled || $lockedService->status !== 'active' || $lockedService->game_id !== $lockedGame->id) {
                throw ValidationException::withMessages(['service' => 'Dịch vụ không tồn tại hoặc đang tạm tắt.']);
            }

            $package = GameServicePackage::query()
                ->whereKey((int) $payload['package_id'])
                ->where('game_service_id', $lockedService->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if (! $package instanceof GameServicePackage) {
                throw ValidationException::withMessages(['package_id' => 'Gói dịch vụ không tồn tại hoặc đang tạm tắt.']);
            }

            $price = GameServicePackagePrice::query()
                ->where('game_service_package_id', $package->id)
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $price instanceof GameServicePackagePrice) {
                throw ValidationException::withMessages(['package_id' => 'Gói dịch vụ chưa có giá đang hoạt động.']);
            }

            $server = GameServer::query()
                ->whereKey((int) $payload['server_id'])
                ->where('game_id', $lockedGame->id)
                ->active()
                ->whereHas('gameServices', fn ($query) => $query->whereKey($lockedService->id))
                ->lockForUpdate()
                ->first();

            if (! $server instanceof GameServer) {
                throw ValidationException::withMessages(['server_id' => 'Máy chủ không nhận dịch vụ này.']);
            }

            $quantity = $price->quantity_enabled ? (int) $payload['quantity'] : 1;
            $minimum = $price->quantity_enabled ? $price->min_quantity : 1;
            $maximum = $price->quantity_enabled ? $price->max_quantity : 1;

            if ($quantity < $minimum || $quantity > $maximum) {
                throw ValidationException::withMessages(['quantity' => "Số lượng phải từ {$minimum} đến {$maximum}."]);
            }

            $payloadKeys = collect($lockedService->payload_fields ?? [])->pluck('key')->filter()->all();
            $recipientPayload = Arr::only(is_array($payload['payload'] ?? null) ? $payload['payload'] : [], $payloadKeys);
            $note = trim((string) ($payload['note'] ?? ''));
            if ($note !== '') {
                $recipientPayload['note'] = $note;
            }
            $email = Str::lower(trim((string) ($user?->email ?? $payload['email'] ?? '')));

            if ($email === '') {
                throw ValidationException::withMessages(['email' => 'Vui lòng nhập email để nhận thông tin đơn.']);
            }

            $totalAmount = $price->price * $quantity;
            $collaboratorTotalCost = $price->collaborator_price * $quantity;
            $taxConfiguration = $this->taxConfigurationService->current();
            $profit = $this->orderProfitCalculator->calculate(
                costPrice: $collaboratorTotalCost,
                salePrice: $totalAmount,
                vatRate: $taxConfiguration['vat_rate'],
                pitRate: $taxConfiguration['pit_rate'],
                taxEnabled: $taxConfiguration['enabled'],
                calculationType: $taxConfiguration['calculation_type'],
            );

            return GameServiceOrder::query()->create([
                'user_id' => $user?->id,
                'game_id' => $lockedGame->id,
                'game_service_id' => $lockedService->id,
                'game_service_package_id' => $package->id,
                'game_service_package_price_id' => $price->id,
                'game_server_id' => $server->id,
                'email' => $email,
                'game_name' => $lockedGame->name,
                'service_name' => $lockedService->name,
                'package_name' => $package->name,
                'price_label' => $package->name,
                'server_name' => $server->name,
                'payload' => $recipientPayload,
                'quantity' => $quantity,
                'unit_price' => $price->price,
                'total_amount' => $totalAmount,
                'collaborator_unit_cost' => $price->collaborator_price,
                'collaborator_total_cost' => $collaboratorTotalCost,
                'gross_profit' => $profit['gross_profit'],
                'tax_enabled' => $taxConfiguration['enabled'],
                'tax_calculation_type' => $taxConfiguration['calculation_type']->value,
                'vat_rate' => $taxConfiguration['vat_rate'],
                'pit_rate' => $taxConfiguration['pit_rate'],
                'estimated_vat' => $profit['estimated_vat'],
                'estimated_pit' => $profit['estimated_pit'],
                'estimated_tax' => $profit['estimated_tax'],
                'net_profit' => $profit['net_profit'],
                'profit_margin' => $profit['profit_margin'],
                'status' => 'pending',
            ]);
        }, 3);
    }
}
