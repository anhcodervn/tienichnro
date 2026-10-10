<?php

namespace App\Models;

use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected $fillable = ['wallet_id', 'actor_id', 'direction', 'amount', 'balance_before', 'balance_after', 'idempotency_key', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer', 'actor_id' => 'integer'];
    }
}
