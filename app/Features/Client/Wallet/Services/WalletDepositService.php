<?php

namespace App\Features\Client\Wallet\Services;

use App\Events\WalletDepositCredited;
use App\Exceptions\ApiException;
use App\Features\Recharge\Services\ApiBankVnPartnerService;
use App\Features\Recharge\Services\RechargeConfigService;
use App\Features\Reporting\Services\DiscordReportService;
use App\Models\ConfigRecharge;
use App\Models\Notification;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletDepositService
{
    public function __construct(
        private readonly RechargeConfigService $rechargeConfigService,
        private readonly ApiBankVnPartnerService $apiBankVnPartnerService,
        private readonly DiscordReportService $discordReportService,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function clientConfig(User $user): ?array
    {
        $config = $this->rechargeConfigService->current();

        if ($config === null || ! $config->is_active) {
            return null;
        }

        return [
            'config' => $config,
            'transfer_content' => $this->rechargeConfigService->isApiBankVnProvider($config)
                ? null
                : $this->rechargeConfigService->transferContentFor($user, $config),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function clientConfigs(User $user): array
    {
        return $this->rechargeConfigService->active()
            ->map(function (ConfigRecharge $config) use ($user): array {
                return [
                    'config' => $config,
                    'transfer_content' => $this->rechargeConfigService->isApiBankVnProvider($config)
                        ? null
                        : $this->rechargeConfigService->transferContentFor($user, $config),
                ];
            })
            ->values()
            ->all();
    }

    public function createRequest(User $user, float $amount, ?int $configId = null): PaymentTransaction
    {
        $config = $this->rechargeConfigService->resolveActiveById($configId);

        if ($config === null || ! $config->is_active) {
            throw new ApiException('Hệ thống chưa cấu hình nhận tiền hoặc đang tạm tắt.', 422);
        }

        return $this->rechargeConfigService->isApiBankVnProvider($config)
            ? $this->createApiBankVnRequest($user, $config, $amount)
            : $this->createManualRequest($user, $config, $amount);
    }

    public function confirmRequest(PaymentTransaction $paymentTransaction, User $user): PaymentTransaction
    {
        $owned = $this->ownedTransaction($paymentTransaction, $user);

        if ($owned->status === 'success') {
            return $owned;
        }

        if ($this->usesApiBankVn($owned)) {
            return $this->syncPartnerTransaction($owned, $user, true);
        }

        $raw = is_array($owned->raw_data) ? $owned->raw_data : [];
        $raw['confirmed_at'] = now()->toISOString();

        $owned->forceFill([
            'status' => $owned->status === 'pending' ? 'matched' : $owned->status,
            'raw_data' => $raw,
        ])->save();

        return $owned->refresh();
    }

    public function paginateRequests(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = min(max((int) ($filters['per_page'] ?? 10), 1), 100);
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['search'] ?? ''));

        $transactions = $user->paymentTransactions()
            ->when($search !== '', fn (Builder $query) => $query->where('transaction_code', 'like', "%{$search}%"))
            ->when($status !== '' && $status !== 'all', function (Builder $query) use ($status): void {
                match ($status) {
                    'processing' => $query->where('status', 'matched'),
                    'paid' => $query->where('status', 'success'),
                    'expired' => $query->where('status', 'cancelled')->where('raw_data->cancel_reason', 'expired'),
                    default => $query->where('status', $status),
                };
            })
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        $transactions->setCollection(
            $transactions->getCollection()->map(fn (PaymentTransaction $transaction): PaymentTransaction => $this->syncPartnerTransactionIfNeeded($transaction, $user))
        );

        return $transactions;
    }

    public function latestPendingRequest(User $user): ?PaymentTransaction
    {
        return $user->paymentTransactions()
            ->whereNull('order_id')
            ->whereIn('status', ['pending', 'matched'])
            ->latest('id')
            ->first([
                'id',
                'user_id',
                'transaction_code',
                'amount',
                'status',
                'created_at',
            ]);
    }

    public function syncTransactionStatus(PaymentTransaction $paymentTransaction): PaymentTransaction
    {
        $user = User::query()->find($paymentTransaction->user_id);

        if (! $user instanceof User) {
            return $paymentTransaction;
        }

        return $this->syncPartnerTransactionIfNeeded($paymentTransaction, $user);
    }

    public function handleApiBankVnCallback(array $payload, array $rawPayload = []): ?PaymentTransaction
    {
        $paymentTransaction = $this->findApiBankVnTransactionForCallback($payload);

        if (! $paymentTransaction instanceof PaymentTransaction) {
            return null;
        }

        $partnerOrder = $this->buildPartnerOrderFromCallbackPayload($payload, $rawPayload, $paymentTransaction);

        return $this->applyPartnerOrderUpdate($paymentTransaction, $partnerOrder, true);
    }

    public function ownedTransaction(PaymentTransaction $paymentTransaction, User $user): PaymentTransaction
    {
        abort_unless($paymentTransaction->user_id === $user->id, 404);

        return $paymentTransaction;
    }

    private function createManualRequest(User $user, ConfigRecharge $config, float $amount): PaymentTransaction
    {
        $transactionCode = 'DEP'.strtoupper(Str::random(10));
        $transferContent = $this->rechargeConfigService->transferContentFor($user, $config);
        $qrUrl = $this->rechargeConfigService->buildQrUrlForTransfer($config, $amount, $user->id, $transferContent);

        return PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'bank_code' => $config->bank_name,
            'account_number' => $config->account_number,
            'transaction_code' => $transactionCode,
            'amount' => $amount,
            'content' => $transferContent,
            'status' => 'pending',
            'raw_data' => [
                'provider' => 'manual',
                'method_id' => 'bank_transfer',
                'method_name' => 'Chuyển khoản ngân hàng',
                'recharge_config_id' => $config->id,
                'account_name' => $config->account_name,
                'qr_template' => $config->qr_template,
                'qr_url' => $qrUrl,
                'transfer_prefix' => $config->transfer_prefix,
                'bonus_amount' => 0,
                'confirmed_at' => null,
                'expires_at' => now()->addDay()->toISOString(),
            ],
        ]);
    }

    private function createApiBankVnRequest(User $user, ConfigRecharge $config, float $amount): PaymentTransaction
    {
        $transactionCode = 'DEP'.strtoupper(Str::random(10));
        $requestedTransferContent = $this->rechargeConfigService->transferContentFor($user, $config);
        $partnerOrder = $this->apiBankVnPartnerService->createRechargeOrder(
            $config,
            $amount,
            $transactionCode,
            $requestedTransferContent,
        );
        $resolvedTransferContent = $requestedTransferContent;

        return PaymentTransaction::query()->create([
            'user_id' => $user->id,
            'bank_code' => (string) ($partnerOrder['bank_name'] ?? $config->bank_name),
            'account_number' => (string) ($partnerOrder['account_number'] ?? $config->account_number),
            'transaction_code' => $transactionCode,
            'amount' => $amount,
            'content' => $resolvedTransferContent,
            'status' => $this->mapPartnerStatusToLocal((string) ($partnerOrder['status'] ?? 'pending')),
            'raw_data' => [
                'provider' => 'apibankvn_api',
                'method_id' => 'apibankvn_api',
                'method_name' => 'Apibankvn API',
                'recharge_config_id' => $config->id,
                'transfer_prefix' => $this->rechargeConfigService->normalizePrefix((string) $config->transfer_prefix),
                'transfer_content' => $requestedTransferContent,
                'requested_transfer_prefix' => $this->rechargeConfigService->normalizePrefix((string) $config->transfer_prefix),
                'requested_transfer_content' => $requestedTransferContent,
                'account_name' => $partnerOrder['account_name'] ?? $config->account_name,
                'bonus_amount' => 0,
                'confirmed_at' => null,
                'expires_at' => $partnerOrder['expires_at'] ?? now()->addHour()->toISOString(),
                'remote_order' => $partnerOrder,
                'remote_order_code' => $partnerOrder['order_code'] ?? null,
                'remote_status' => $partnerOrder['status'] ?? null,
                'client_order_code' => $partnerOrder['client_order_code'] ?? $transactionCode,
            ],
        ]);
    }

    private function syncPartnerTransactionIfNeeded(PaymentTransaction $paymentTransaction, User $user): PaymentTransaction
    {
        if (! $this->usesApiBankVn($paymentTransaction)) {
            return $paymentTransaction;
        }

        if (! in_array($paymentTransaction->status, ['pending', 'matched'], true)) {
            return $paymentTransaction;
        }

        return $this->syncPartnerTransaction($paymentTransaction, $user, false);
    }

    private function syncPartnerTransaction(PaymentTransaction $paymentTransaction, User $user, bool $markConfirmed): PaymentTransaction
    {
        $raw = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];
        $remoteOrderCode = (string) ($raw['remote_order_code'] ?? '');

        if ($remoteOrderCode === '') {
            return $paymentTransaction;
        }

        $configId = isset($raw['recharge_config_id']) ? (int) $raw['recharge_config_id'] : null;
        $config = $this->rechargeConfigService->resolveById($configId);

        if (! $config instanceof ConfigRecharge || ! $this->apiBankVnPartnerService->isConfigured($config)) {
            return $paymentTransaction;
        }

        $partnerOrder = $this->apiBankVnPartnerService->fetchRechargeOrder($config, $remoteOrderCode);

        return $this->applyPartnerOrderUpdate($paymentTransaction, $partnerOrder, $markConfirmed);
    }

    /**
     * @param  array<string, mixed>  $partnerOrder
     */
    private function creditSuccessfulTransaction(PaymentTransaction $paymentTransaction, array $partnerOrder, bool $markConfirmed): PaymentTransaction
    {
        $eventPayload = null;
        $creditedTransaction = DB::transaction(function () use ($paymentTransaction, $partnerOrder, $markConfirmed, &$eventPayload): PaymentTransaction {
            $lockedTransaction = PaymentTransaction::query()
                ->whereKey($paymentTransaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTransaction->status === 'success') {
                return $lockedTransaction;
            }

            $user = User::query()->findOrFail($lockedTransaction->user_id);

            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', Wallet::TYPE_MAIN)
                ->lockForUpdate()
                ->first();

            if (! $wallet instanceof Wallet) {
                $wallet = $user->wallets()->create([
                    'type' => Wallet::TYPE_MAIN,
                    'balance' => 0,
                    'hold_balance' => 0,
                    'total_recharge' => 0,
                    'total_spent' => 0,
                ]);

                $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $balanceBefore = (float) $wallet->balance;
            $creditAmount = (float) $lockedTransaction->amount;
            $balanceAfter = $balanceBefore + $creditAmount;

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'total_recharge' => (float) $wallet->total_recharge + $creditAmount,
            ])->save();

            WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => 'credit',
                'amount' => $creditAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => PaymentTransaction::class,
                'reference_id' => $lockedTransaction->id,
                'description' => 'Nạp tiền thành công qua apibankvn.com mã:'.$lockedTransaction->transaction_code,
                'status' => 'success',
            ]);

            $raw = is_array($lockedTransaction->raw_data) ? $lockedTransaction->raw_data : [];
            $raw['remote_order'] = $partnerOrder;
            $raw['remote_order_code'] = $partnerOrder['order_code'] ?? ($raw['remote_order_code'] ?? null);
            $raw['remote_status'] = $partnerOrder['status'] ?? 'paid';
            $raw['account_name'] = $partnerOrder['account_name'] ?? ($raw['account_name'] ?? null);
            $raw['expires_at'] = $partnerOrder['expires_at'] ?? ($raw['expires_at'] ?? null);
            $raw['paid_at'] = $partnerOrder['paid_at'] ?? now()->toISOString();
            $raw['callback_metadata'] = $partnerOrder['metadata'] ?? ($raw['callback_metadata'] ?? null);
            $raw['confirmed_at'] = $markConfirmed
                ? now()->toISOString()
                : ($raw['confirmed_at'] ?? null);

            $lockedTransaction->forceFill([
                'bank_code' => (string) ($partnerOrder['bank_name'] ?? $lockedTransaction->bank_code),
                'account_number' => (string) ($partnerOrder['account_number'] ?? $lockedTransaction->account_number),
                'content' => (string) ($raw['requested_transfer_content'] ?? $raw['transfer_content'] ?? $lockedTransaction->content),
                'status' => 'success',
                'raw_data' => $raw,
            ])->save();

            $notification = Notification::query()->create([
                'user_id' => $user->id,
                'scope' => Notification::SCOPE_USER,
                'title' => 'Nạp tiền thành công',
                'content' => number_format($creditAmount, 0, ',', '.').'đ đã được cộng vào ví của bạn.',
                'redirect_url' => "/wallet?view=payment&request={$lockedTransaction->id}&from=deposit",
                'type' => 'success',
                'is_read' => false,
            ]);

            $eventPayload = [
                'user_id' => $user->id,
                'payment_transaction_id' => $lockedTransaction->id,
                'transaction_code' => $lockedTransaction->transaction_code,
                'amount' => number_format($creditAmount, 2, '.', ''),
                'balance_before' => number_format($balanceBefore, 2, '.', ''),
                'balance' => (string) $wallet->balance,
                'total_recharge' => (string) $wallet->total_recharge,
                'credited_at' => now()->toISOString(),
                'notification' => [
                    'id' => $notification->id,
                    'scope' => $notification->scope,
                    'title' => $notification->title,
                    'content' => $notification->content,
                    'redirect_url' => $notification->redirect_url,
                    'type' => $notification->type,
                    'is_read' => false,
                    'created_at' => $notification->created_at?->toDateTimeString(),
                ],
            ];

            return $lockedTransaction->refresh();
        });

        if (is_array($eventPayload)) {
            WalletDepositCredited::dispatch(
                userId: $eventPayload['user_id'],
                paymentTransactionId: $eventPayload['payment_transaction_id'],
                transactionCode: $eventPayload['transaction_code'],
                amount: $eventPayload['amount'],
                balance: $eventPayload['balance'],
                totalRecharge: $eventPayload['total_recharge'],
                creditedAt: $eventPayload['credited_at'],
                notification: $eventPayload['notification'],
            );

            $this->discordReportService->queue(
                channel: 'activity',
                title: 'Người dùng nạp tiền thành công',
                details: [
                    'Mã giao dịch' => $eventPayload['transaction_code'],
                    'User ID' => $eventPayload['user_id'],
                    'Số tiền' => number_format((float) $eventPayload['amount'], 0, ',', '.').' đ',
                    'Số dư trước' => number_format((float) $eventPayload['balance_before'], 0, ',', '.').' đ',
                    'Số dư sau' => number_format((float) $eventPayload['balance'], 0, ',', '.').' đ',
                ],
                dedupeKey: 'wallet-deposit:'.$eventPayload['payment_transaction_id'].':credited',
            );
        }

        return $creditedTransaction;
    }

    private function usesApiBankVn(PaymentTransaction $paymentTransaction): bool
    {
        $raw = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];

        return ($raw['provider'] ?? null) === 'apibankvn_api';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findApiBankVnTransactionForCallback(array $payload): ?PaymentTransaction
    {
        $clientOrderCode = trim((string) ($payload['client_order_code'] ?? ''));

        if ($clientOrderCode !== '') {
            return PaymentTransaction::query()
                ->where('transaction_code', $clientOrderCode)
                ->where('raw_data->provider', 'apibankvn_api')
                ->latest('id')
                ->first();
        }

        $remoteOrderCode = trim((string) ($payload['order_code'] ?? ''));

        if ($remoteOrderCode !== '') {
            return PaymentTransaction::query()
                ->where('raw_data->provider', 'apibankvn_api')
                ->where('raw_data->remote_order_code', $remoteOrderCode)
                ->latest('id')
                ->first();
        }

        $transferContent = trim((string) ($payload['transfer_content'] ?? ''));

        if ($transferContent !== '') {
            return PaymentTransaction::query()
                ->where('content', $transferContent)
                ->where('raw_data->provider', 'apibankvn_api')
                ->latest('id')
                ->first();
        }

        $transactionDescription = trim((string) ($payload['transaction_description'] ?? ''));

        if ($transactionDescription !== '') {
            return $this->findApiBankVnTransactionByDescription(
                description: $transactionDescription,
                amount: isset($payload['amount']) ? (float) $payload['amount'] : null,
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $rawPayload
     * @return array<string, mixed>
     */
    private function buildPartnerOrderFromCallbackPayload(array $payload, array $rawPayload, PaymentTransaction $paymentTransaction): array
    {
        $raw = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];
        $transactionDescription = trim((string) ($payload['transaction_description'] ?? ''));

        return [
            'order_code' => $payload['order_code'] ?? ($raw['remote_order_code'] ?? null),
            'client_order_code' => $payload['client_order_code'] ?? ($raw['client_order_code'] ?? $paymentTransaction->transaction_code),
            'amount' => $payload['amount'] ?? $paymentTransaction->amount,
            'bank_name' => $payload['bank_name'] ?? $paymentTransaction->bank_code,
            'account_number' => $payload['account_number'] ?? $paymentTransaction->account_number,
            'account_name' => $payload['account_name'] ?? ($raw['account_name'] ?? null),
            'transfer_content' => $payload['transfer_content'] ?? ($raw['requested_transfer_content'] ?? $paymentTransaction->content),
            'status' => $payload['status'] ?? ($raw['remote_status'] ?? 'pending'),
            'paid_at' => $payload['paid_at'] ?? null,
            'requested_at' => $payload['requested_at'] ?? null,
            'expires_at' => $payload['expires_at'] ?? ($raw['expires_at'] ?? null),
            'metadata' => [
                'callback_payload' => $rawPayload,
                'transaction_id' => $payload['transaction_id'] ?? null,
                'transaction_description' => $transactionDescription !== '' ? $transactionDescription : null,
                'transaction_time' => $payload['transaction_time'] ?? null,
                'transaction_type' => $payload['transaction_type'] ?? null,
                'bank_account_id' => $payload['bank_account_id'] ?? null,
                'webhook_id' => $payload['webhook_id'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $partnerOrder
     */
    private function applyPartnerOrderUpdate(PaymentTransaction $paymentTransaction, array $partnerOrder, bool $markConfirmed): PaymentTransaction
    {
        $nextStatus = $this->mapPartnerStatusToLocal((string) ($partnerOrder['status'] ?? 'pending'));

        if ($nextStatus === 'success') {
            return $this->creditSuccessfulTransaction($paymentTransaction, $partnerOrder, $markConfirmed);
        }

        $raw = is_array($paymentTransaction->raw_data) ? $paymentTransaction->raw_data : [];
        $raw['remote_order'] = $partnerOrder;
        $raw['remote_order_code'] = $partnerOrder['order_code'] ?? ($raw['remote_order_code'] ?? null);
        $raw['remote_status'] = $partnerOrder['status'] ?? null;
        $raw['account_name'] = $partnerOrder['account_name'] ?? ($raw['account_name'] ?? null);
        $raw['expires_at'] = $partnerOrder['expires_at'] ?? ($raw['expires_at'] ?? null);
        $raw['callback_metadata'] = $partnerOrder['metadata'] ?? ($raw['callback_metadata'] ?? null);

        if ($markConfirmed) {
            $raw['confirmed_at'] = now()->toISOString();
        }

        $cancelReason = $nextStatus === 'cancelled' && (($partnerOrder['status'] ?? null) === 'expired')
            ? 'expired'
            : ($raw['cancel_reason'] ?? null);

        if ($cancelReason !== null) {
            $raw['cancel_reason'] = $cancelReason;
        }

        $paymentTransaction->forceFill([
            'bank_code' => (string) ($partnerOrder['bank_name'] ?? $paymentTransaction->bank_code),
            'account_number' => (string) ($partnerOrder['account_number'] ?? $paymentTransaction->account_number),
            'content' => (string) ($raw['requested_transfer_content'] ?? $raw['transfer_content'] ?? $paymentTransaction->content),
            'status' => $nextStatus,
            'raw_data' => $raw,
        ])->save();

        return $paymentTransaction->refresh();
    }

    private function mapPartnerStatusToLocal(string $status): string
    {
        return match (Str::lower(trim($status))) {
            'processing' => 'matched',
            'paid' => 'success',
            'failed' => 'failed',
            'cancelled', 'canceled', 'expired' => 'cancelled',
            default => 'pending',
        };
    }

    private function findApiBankVnTransactionByDescription(string $description, ?float $amount = null): ?PaymentTransaction
    {
        $normalizedDescription = Str::upper(trim($description));

        if ($normalizedDescription === '') {
            return null;
        }

        /** @var Collection<int, PaymentTransaction> $candidates */
        $candidates = PaymentTransaction::query()
            ->where('raw_data->provider', 'apibankvn_api')
            ->whereIn('status', ['pending', 'matched'])
            ->when($amount !== null, fn (Builder $query) => $query->where('amount', $amount))
            ->latest('id')
            ->get();

        return $candidates->first(function (PaymentTransaction $transaction) use ($normalizedDescription): bool {
            return $this->descriptionMatchesTransactionContent($normalizedDescription, $transaction);
        });
    }

    private function descriptionMatchesTransactionContent(string $normalizedDescription, PaymentTransaction $transaction): bool
    {
        $raw = is_array($transaction->raw_data) ? $transaction->raw_data : [];
        $expectedContent = trim((string) ($raw['requested_transfer_content'] ?? $raw['transfer_content'] ?? $transaction->content));

        if ($expectedContent === '') {
            return false;
        }

        $normalizedContent = Str::upper($expectedContent);

        if (! str_contains($normalizedDescription, $normalizedContent)) {
            return false;
        }

        $expectedPrefix = trim((string) ($raw['requested_transfer_prefix'] ?? $raw['transfer_prefix'] ?? ''));

        if ($expectedPrefix === '') {
            return true;
        }

        return str_contains($normalizedDescription, Str::upper($expectedPrefix));
    }
}
