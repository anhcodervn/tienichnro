<?php

namespace App\Features\Admin\RechargeHistory\Resources;

use App\Models\PaymentTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentTransaction
 */
class AdminRechargeHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $raw = is_array($this->raw_data) ? $this->raw_data : [];
        $status = match ($this->status) {
            'matched' => 'processing',
            'success' => 'paid',
            'cancelled' => (($raw['cancel_reason'] ?? null) === 'expired' ? 'expired' : 'cancelled'),
            default => $this->status,
        };

        return [
            'id' => $this->id,
            'transaction_code' => $this->transaction_code,
            'amount' => (float) $this->amount,
            'bonus_amount' => (float) ($raw['bonus_amount'] ?? 0),
            'bonus_percent' => (float) ($raw['bonus_percent'] ?? 0),
            'credited_amount' => (float) ($raw['credited_amount'] ?? ((float) $this->amount + (float) ($raw['bonus_amount'] ?? 0))),
            'content' => $this->content,
            'status' => $status,
            'bank_name' => $this->bank_code,
            'account_number' => $this->account_number,
            'account_name' => $raw['account_name'] ?? null,
            'confirmed_at' => $raw['confirmed_at'] ?? null,
            'expires_at' => $raw['expires_at'] ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null,
        ];
    }
}
