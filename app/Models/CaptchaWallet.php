<?php

namespace App\Models;

use Database\Factories\CaptchaWalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaWallet extends Model
{
    /** @use HasFactory<CaptchaWalletFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'balance', 'reserved'];

    protected $attributes = ['balance' => 0, 'reserved' => 0];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'reserved' => 'integer', 'user_id' => 'integer'];
    }
}
