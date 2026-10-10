<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseChallenge extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $table = 'license_challenges';

    protected $fillable = ['id', 'nonce_hash', 'expires_at', 'consumed_at'];

    protected $hidden = ['nonce_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }
}
