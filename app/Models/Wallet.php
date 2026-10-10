<?php

namespace App\Models;

use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'balance'];

    protected $attributes = ['balance' => 0];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'balance' => 'integer'];
    }
}
