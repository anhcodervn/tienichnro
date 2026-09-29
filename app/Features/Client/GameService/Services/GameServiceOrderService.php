<?php

namespace App\Features\Client\GameService\Services;

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
            $email = Str::lower(trim((string) ($user?->email ?? $payload['email'] ?? '')));

            if ($email === '') {
                throw ValidationException::withMessages(['email' => 'Vui lòng nhập email để nhận thông tin đơn.']);
            }

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
                'total_amount' => $price->price * $quantity,
                'status' => 'pending',
            ]);
        }, 3);
    }
}
