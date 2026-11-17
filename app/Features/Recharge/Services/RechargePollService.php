<?php

namespace App\Features\Recharge\Services;

use App\Models\WalletRechargeOrder;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RechargePollService
{
    public function __construct(private readonly RechargeOrderService $orders) {}

    public function run(): array
    {
        $lock = Cache::lock('recharge:poll', 60);
        abort_unless($lock->get(), 409, 'Một lượt kiểm tra giao dịch đang chạy.');
        $result = ['checked' => 0, 'credited' => 0, 'errors' => 0, 'at' => now()->toIso8601String()];
        $deadline = microtime(true) + 20;
        try {
            $pending = WalletRechargeOrder::query()->whereIn('status', ['pending', 'processing'])->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subSeconds(30)))
                ->orderBy('last_checked_at')->orderBy('id')->limit(10)->get();
            foreach ($pending as $order) {
                if (microtime(true) > $deadline) {
                    break;
                }
                $result['checked']++;
                try {
                    $updated = $this->orders->check($order);
                    $result['credited'] += $updated->status === 'paid' ? 1 : 0;
                } catch (Throwable $exception) {
                    $result['errors']++;
                    $allowed = ['PROVIDER_CONNECTION', 'PROVIDER_HTTP', 'PROVIDER_RESPONSE', 'PROVIDER_ORDER', 'PROVIDER_MISMATCH', 'PROVIDER_STATE', 'PROVIDER_PAYMENT', 'DUPLICATE_TRANSACTION'];
                    $code = $exception instanceof RuntimeException && in_array($exception->getMessage(), $allowed, true) ? $exception->getMessage() : 'CHECK_FAILED';
                    WalletRechargeOrder::query()->whereKey($order->id)->whereIn('status', ['pending', 'processing'])->update(['last_checked_at' => now(), 'last_error' => $code]);
                }
            }
            WalletRechargeOrder::query()->where('status', 'creating')->where('created_at', '<', now()->subMinutes(5))->update(['status' => 'creation_failed', 'last_error' => 'CREATE_UNCONFIRMED']);
            Cache::put('recharge:last_check', $result, now()->addDay());
        } finally {
            $lock->release();
        }

        return $result;
    }
}
