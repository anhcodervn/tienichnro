<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'key_type',
        'name',
        'api_key',
        'api_secret_hash',
        'permissions',
        'ip_whitelist',
        'status',
        'last_used_at',
        'expired_at',
    ];

    protected $hidden = [
        'api_secret_hash',
        'api_secret_encrypted',
    ];

    protected $attributes = [
        'key_type' => 'topup',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'ip_whitelist' => 'array',
            'last_used_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
