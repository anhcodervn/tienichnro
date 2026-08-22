<?php

namespace App\Features\Client\Wallet\Resources;

use App\Features\Recharge\Services\RechargeConfigService;
use App\Models\ConfigRecharge;
use App\Models\PaymentTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentTransaction
 */
class DepositRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        $raw = is_array($this->raw_data) ? $this->raw_data : [];
        $rechargeConfigService = app(RechargeConfigService::class);
        $status = match ($this->status) {
            'matched' => 'processing',
            'success' => 'paid',
            'cancelled' => (($raw['cancel_reason'] ?? null) === 'expired' ? 'expired' : 'cancelled'),
            default => $this->status,
        };

        return [
            'id' => $this->id,
            'code' => $this->transaction_code,
            'created_at' => $this->created_at?->toISOString(),
            'method' => [
                'id' => 'bank_transfer',
                'name' => 'Chuyển khoản ngân hàng',
            ],
            'amount' => (float) $this->amount,
            'bonus_amount' => (float) ($raw['bonus_amount'] ?? 0),
            'status' => $status,
            'content' => $this->content,
            'account_number' => $this->account_number,
            'bank_name' => $this->bank_code,
            'account_name' => $raw['account_name'] ?? null,
            'qr_url' => $raw['qr_url'] ?? $this->fallbackQrUrl($raw, $rechargeConfigService),
            'confirmed_at' => $raw['confirmed_at'] ?? null,
            'expires_at' => $raw['expires_at'] ?? null,
            'can_confirm' => in_array($status, ['pending', 'processing'], true),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function fallbackQrUrl(array $raw, RechargeConfigService $rechargeConfigService): ?string
    {
        $configId = isset($raw['recharge_config_id']) ? (int) $raw['recharge_config_id'] : null;

        if (! $configId) {
            return null;
        }

        $config = ConfigRecharge::query()->find($configId);

        if (! $config instanceof ConfigRecharge || blank($config->qr_template)) {
            return null;
        }

        return $rechargeConfigService->replaceTemplate((string) $config->qr_template, [
            'bank_code' => (string) $config->bank_name,
            'bank_name' => (string) $config->bank_name,
            'account_name' => (string) ($raw['account_name'] ?? $config->account_name),
            'account_number' => (string) ($this->account_number ?: $config->account_number),
            'amount' => (string) (int) round((float) $this->amount),
            'user_id' => (string) $this->user_id,
            'prefix' => $rechargeConfigService->normalizePrefix((string) $config->transfer_prefix),
            'nd' => (string) ($this->content ?? ''),
        ]);
    }
}
