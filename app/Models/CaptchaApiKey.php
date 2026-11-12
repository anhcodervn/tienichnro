<?php

namespace App\Models;

use Database\Factories\CaptchaApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaApiKey extends Model
{
    /** @use HasFactory<CaptchaApiKeyFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'token_hash', 'prefix', 'last_used_at'];

    protected $attributes = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }
}
