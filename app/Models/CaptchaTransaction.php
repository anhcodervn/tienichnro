<?php

namespace App\Models;

use Database\Factories\CaptchaTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaTransaction extends Model
{
    /** @use HasFactory<CaptchaTransactionFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'actor_id', 'request_key', 'direction', 'amount', 'balance_before', 'balance_after', 'cost', 'description'];

    protected $attributes = ['cost' => 0];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'cost' => 'integer', 'balance_before' => 'integer', 'balance_after' => 'integer'];
    }
}
