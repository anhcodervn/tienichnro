<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    /** @use HasFactory<AffiliateCommissionFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'tenant_id', 'order_id', 'referrer_id', 'referred_user_id', 'topup_package_id',
        'commission_type', 'rate_value', 'base_amount', 'quantity', 'amount', 'holding_days', 'status',
        'is_flagged', 'hold_reason', 'earned_at', 'available_at', 'reversed_at', 'reversal_reason',
    ];

    protected $attributes = ['status' => self::STATUS_PENDING, 'is_flagged' => false];

    protected function casts(): array
    {
        return [
            'rate_value' => 'integer', 'base_amount' => 'integer', 'quantity' => 'integer', 'amount' => 'integer',
            'holding_days' => 'integer',
            'is_flagged' => 'boolean', 'earned_at' => 'datetime', 'available_at' => 'datetime', 'reversed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }
}
