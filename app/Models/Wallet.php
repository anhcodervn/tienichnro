<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_MAIN = 'main';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'balance',
        'hold_balance',
        'total_recharge',
        'total_spent',
    ];

    protected $attributes = [
        'type' => self::TYPE_MAIN,
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'hold_balance' => 'decimal:2',
            'total_recharge' => 'decimal:2',
            'total_spent' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
