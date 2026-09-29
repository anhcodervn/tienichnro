<?php

namespace App\Models;

use App\Features\Affiliate\Events\AffiliateDashboardUpdated;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateWithdrawalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateWithdrawal extends Model
{
    /** @use HasFactory<AffiliateWithdrawalFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const WALLET_AFFILIATE = 'affiliate';

    public const WALLET_COLLABORATOR = 'collaborator';

    protected $fillable = [
        'tenant_id', 'user_id', 'admin_id', 'amount', 'wallet_type', 'status', 'bank_name', 'bank_account_name',
        'bank_account_number', 'idempotency_key', 'bank_transaction_reference', 'admin_note',
        'approved_at', 'paid_at', 'rejected_at', 'cancelled_at',
    ];

    protected $hidden = ['bank_account_name', 'bank_account_number'];

    protected $attributes = ['status' => self::STATUS_REQUESTED, 'wallet_type' => self::WALLET_AFFILIATE];

    protected static function booted(): void
    {
        static::saved(fn (self $withdrawal): mixed => AffiliateDashboardUpdated::dispatch((int) $withdrawal->user_id));
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'bank_account_name' => 'encrypted', 'bank_account_number' => 'encrypted',
            'approved_at' => 'datetime', 'paid_at' => 'datetime', 'rejected_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
