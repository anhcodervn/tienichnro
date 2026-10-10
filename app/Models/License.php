<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasFactory;

    protected $table = 'licenses';

    protected $fillable = ['product_id', 'plan_id', 'user_id', 'key_hash', 'key_prefix', 'status', 'duration_days', 'activated_at', 'expires_at', 'last_transfer_at', 'generation', 'max_active_devices', 'transfer_cooldown', 'current_device_uuid'];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return ['activated_at' => 'datetime', 'expires_at' => 'datetime', 'last_transfer_at' => 'datetime', 'generation' => 'integer', 'duration_days' => 'integer', 'transfer_cooldown' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LicenseProduct::class, 'product_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicensePlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(LicenseDevice::class, 'license_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LicenseSession::class, 'license_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class, 'license_id');
    }
}
