<?php

namespace App\Features\Client\GameService\Services;

use App\Exceptions\ApiException;
use App\Features\Client\Wallet\Services\WalletService;
use App\Features\Topup\Services\OrderProfitCalculatorService;
use App\Features\Topup\Services\TaxConfigurationService;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Models\GameServiceOrder;
use App\Models\GameServicePackage;
use App\Models\GameServicePackagePrice;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\GameServicePayloadCipher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GameServiceOrderService
{
    public function __construct(
        private readonly TaxConfigurationService $taxConfigurationService,
        private readonly OrderProfitCalculatorService $orderProfitCalculator,
        private readonly GameServicePayloadCipher $payloadCipher,
        private readonly WalletService $walletService,
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

            $order = GameServiceOrder::query()->create([
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
                'payload' => $this->payloadCipher->encrypt($recipientPayload),
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

            if ($user instanceof User) {
                try {
                    $this->walletService->debit(
                        user: $user,
                        amount: $totalAmount,
                        referenceType: GameServiceOrder::class,
                        referenceId: $order->id,
                        description: "Thanh toán đơn dịch vụ game {$order->code}",
                        idempotencyKey: (string) Str::uuid(),
                    );
                } catch (ApiException $exception) {
                    throw ValidationException::withMessages(['wallet' => $exception->getMessage()]);
                }
            }

            return $order;
        }, 3);
    }

    /**
     * @return array{order: GameServiceOrder, refunded_amount: int}
     *
     * @throws AuthorizationException
     */
    public function cancelPending(GameServiceOrder $order, User $user): array
    {
        return DB::transaction(function () use ($order, $user): array {
            $lockedOrder = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if ($lockedOrder->status !== 'pending') {
                throw ValidationException::withMessages([
                    'order' => 'Chỉ có thể hủy đơn đang chờ tiếp nhận.',
                ]);
            }

            $refundedAmount = $this->refundWalletPayment($lockedOrder, $user);

            $lockedOrder->forceFill(['status' => 'cancelled'])->save();

            return [
                'order' => $lockedOrder->refresh(),
                'refunded_amount' => $refundedAmount,
            ];
        }, 3);
    }

    public function refundWalletPayment(GameServiceOrder $order, User $user): int
    {
        $wallet = $this->walletService->getWallet($user);
        $debit = WalletTransaction::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('wallet_id', $wallet->id)
            ->where('type', 'debit')
            ->where('status', 'success')
            ->where('reference_type', GameServiceOrder::class)
            ->where('reference_id', $order->id)
            ->latest('id')
            ->first();

        if (! $debit instanceof WalletTransaction) {
            return 0;
        }

        $debitedAmount = (int) $debit->amount;
        if ($debitedAmount !== $order->total_amount) {
            throw ValidationException::withMessages([
                'order' => 'Giao dịch thanh toán của đơn không khớp. Vui lòng liên hệ quản trị viên.',
            ]);
        }

        $existingRefund = WalletTransaction::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('wallet_id', $wallet->id)
            ->where('type', 'refund')
            ->where('status', 'success')
            ->where('reference_type', GameServiceOrder::class)
            ->where('reference_id', $order->id)
            ->exists();

        if ($existingRefund) {
            return 0;
        }

        $this->walletService->refund(
            user: $user,
            amount: $debitedAmount,
            referenceType: GameServiceOrder::class,
            referenceId: $order->id,
            description: "Hoàn tiền đơn dịch vụ game {$order->code}",
            idempotencyKey: (string) Str::uuid(),
        );

        return $debitedAmount;
    }
}
