<?php

namespace App\Models;

use Database\Factories\MemberLevelOrderCreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLevelOrderCredit extends Model
{
    /** @use HasFactory<MemberLevelOrderCreditFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'order_id', 'amount', 'occurred_at', 'reversed_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
