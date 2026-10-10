<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseNonce extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'license_nonces';

    protected $fillable = ['device_id', 'nonce_hash', 'expires_at'];

    protected $hidden = ['nonce_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
