<?php

namespace App\Features\Recharge\Services;

use App\Features\Client\Wallet\Services\WalletService;
use App\Models\User;
use App\Models\WalletRechargeOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RechargeOrderService
{
    public function __construct(private readonly RechargeConfigurationService $configuration, private readonly ApiBankVnService $provider, private readonly WalletService $wallets) {}

    public function create(User $user, int $amount, string $requestId): WalletRechargeOrder
    {
        $config = $this->configuration->snapshot();
        abort_unless($config['enabled'] && $config['credentials_saved'] && $config['bank_id'] > 0, 409, 'Nạp tự động chưa được bật.');
        abort_unless($amount >= 10000 && $amount <= 50000000, 422);
        $credentials = $this->configuration->credentials();
        [$order, $new] = DB::transaction(function () use ($user, $amount, $requestId, $config, $credentials): array {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($owner->status === 'active', 403);
            $existing = WalletRechargeOrder::query()->where('user_id', $owner->id)->where('request_id', $requestId)->first();
            if ($existing) {
                abort_unless($existing->amount === $amount, 409, 'Mã yêu cầu đã được dùng với số tiền khác.');

                return [$existing, false];
            }
            abort_if(WalletRechargeOrder::query()->where('user_id', $owner->id)->whereIn('status', ['creating', 'pending', 'processing'])->count() >= 5, 409, 'Bạn đang có 5 lệnh nạp chưa hoàn tất. Vui lòng thanh toán hoặc chờ hết hạn.');
            do {
                $code = 'TNR'.Str::upper(Str::random(7));
            } while (WalletRechargeOrder::query()->where('code', $code)->exists());
            $order = new WalletRechargeOrder(['user_id' => $owner->id, 'request_id' => $requestId, 'code' => $code, 'bank_id' => $config['bank_id'], 'amount' => $amount]);
            $order->forceFill(['api_key' => $credentials['key'], 'api_secret' => $credentials['secret'], 'status' => 'creating', 'transfer_content' => $code,
                'bank_code' => $config['bank']['bank_code'] ?? null,
                'account_number' => $config['bank']['account_number'] ?? '', 'account_name' => $config['bank']['account_name'] ?? '', 'bank_name' => $config['bank']['bank_name'] ?? ''])->save();

            return [$order, true];
        }, 3);
        if (! $new) {
            return $order;
        }
        try {
            $remote = $this->provider->create($credentials, $order->bank_id, $amount, $order->code);
            $this->assertMatches($order, $remote, true);
            if (! in_array($remote['status'] ?? '', ['pending', 'processing'], true)) {
                throw new RuntimeException('PROVIDER_STATE');
            }
            if (! is_string($remote['expires_at'] ?? null) || $remote['expires_at'] === '') {
                throw new RuntimeException('PROVIDER_RESPONSE');
            }
            $expires = CarbonImmutable::parse($remote['expires_at'])->setTimezone(config('app.timezone'));
            if ($expires->isPast()) {
                throw new RuntimeException('PROVIDER_STATE');
            }
            $order->forceFill(['provider_code' => $remote['order_code'], 'status' => $remote['status'], 'expires_at' => $expires])->save();
        } catch (\Throwable) {
            $order->forceFill(['provider_code' => null, 'status' => 'creation_failed', 'last_error' => 'CREATE_UNCONFIRMED'])->save();
        }

        return $order;
    }

    public function check(WalletRechargeOrder $order): WalletRechargeOrder
    {
        if (! in_array($order->status, ['pending', 'processing'], true)) {
            return $order;
        }
        $remote = $this->provider->order(['key' => $order->api_key, 'secret' => $order->api_secret], $order->provider_code);
        $this->assertMatches($order, $remote);
        $status = $remote['status'] ?? '';
        if (! in_array($status, ['pending', 'processing', 'paid', 'expired', 'failed', 'cancelled'], true)) {
            throw new RuntimeException('PROVIDER_STATE');
        }
        if ($status === 'paid' && (! preg_match('/\A[1-9][0-9]{0,19}\z/', (string) ($remote['matched_bank_transaction_id'] ?? '')) || empty($remote['paid_at']))) {
            throw new RuntimeException('PROVIDER_PAYMENT');
        }

        return DB::transaction(function () use ($order, $remote, $status): WalletRechargeOrder {
            $owner = User::query()->lockForUpdate()->findOrFail($order->user_id);
            $locked = WalletRechargeOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($locked->status, ['pending', 'processing'], true)) {
                return $locked;
            }
            $this->assertMatches($locked, $remote);
            if ($status === 'paid') {
                $transactionId = (string) $remote['matched_bank_transaction_id'];
                if (WalletRechargeOrder::query()->where('bank_transaction_id', $transactionId)->whereKeyNot($locked->id)->exists()) {
                    throw new RuntimeException('DUPLICATE_TRANSACTION');
                }
                $paidAt = CarbonImmutable::parse($remote['paid_at'])->setTimezone(config('app.timezone'));
                $ledger = $this->wallets->apply($owner, $locked->amount, 'credit', 'recharge:apibankvn:'.$locked->code, 'Nạp tiền qua APIBankVN #'.$locked->code);
                $locked->forceFill(['wallet_transaction_id' => $ledger->id, 'bank_transaction_id' => $transactionId, 'paid_at' => $paidAt]);
            }
            $locked->forceFill(['status' => $status, 'last_checked_at' => now(), 'last_error' => null])->save();

            return $locked;
        }, 3);
    }

    private function assertMatches(WalletRechargeOrder $order, array $data, bool $initial = false): void
    {
        $amount = (string) ($data['amount'] ?? '');
        if (! preg_match('/\A[0-9]+(?:\.0{1,2})?\z/', $amount) || (int) $amount !== $order->amount ||
            (string) ($data['client_order_code'] ?? '') !== $order->code || (string) ($data['bank_id'] ?? '') !== (string) $order->bank_id ||
            (string) ($data['account_number'] ?? '') !== $order->account_number || (string) ($data['transfer_content'] ?? '') !== $order->transfer_content ||
            ! preg_match('/\A[A-Za-z0-9_-]{1,100}\z/', (string) ($data['order_code'] ?? '')) || (! $initial && $data['order_code'] !== $order->provider_code)) {
            throw new RuntimeException('PROVIDER_MISMATCH');
        }
    }
}
