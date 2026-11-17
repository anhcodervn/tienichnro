<?php

namespace App\Features\Recharge\Services;

use App\Models\WalletRechargeOrder;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class RechargeWebhookService
{
    public function __construct(private readonly RechargeConfigurationService $configuration, private readonly RechargeOrderService $orders) {}

    /** @return array{status: string, code?: string} */
    public function receive(array $data): array
    {
        abort_unless(hash_equals(md5($this->configuration->webhookSecret().(string) $data['bank_id']), strtolower($data['sign'])), 401);
        $transaction = $data['payload']['transaction'];
        if (strtolower($data['event_keyword']) !== 'tnr' || $transaction['type'] !== 'credit' || (int) $transaction['amount'] <= 0) {
            return ['status' => 'ignored'];
        }
        preg_match_all('/(?<![A-Z0-9])(?:TNR[A-Z0-9]{5,7}|TN[A-Z0-9]{24})(?![A-Z0-9])/i', $transaction['description'], $matches);
        $codes = array_unique(array_map('strtoupper', $matches[0]));
        if (count($codes) !== 1) {
            return ['status' => 'ignored'];
        }
        $order = WalletRechargeOrder::query()->where('code', reset($codes))->first();
        if (! $order || $order->bank_id !== (int) $data['bank_id'] || $order->amount !== (int) $transaction['amount']) {
            return ['status' => 'ignored'];
        }
        if (! in_array($order->status, ['pending', 'processing'], true)) {
            return ['status' => 'already_processed'];
        }
        $lock = Cache::lock('recharge:webhook:'.$order->id, 20);
        abort_unless($lock->get(), 503, 'Đang kiểm tra giao dịch. Vui lòng gửi lại webhook.');
        try {
            $updated = $this->orders->check($order, (string) $transaction['id']);
        } catch (RuntimeException) {
            abort(503, 'Chưa xác minh được giao dịch. Vui lòng gửi lại webhook.');
        } finally {
            $lock->release();
        }

        return ['status' => $updated->status, 'code' => $updated->code];
    }
}
