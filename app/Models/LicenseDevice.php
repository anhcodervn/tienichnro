<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseDevice extends Model
{
    use HasFactory;

    protected $table = 'license_devices';

    protected $fillable = ['license_id', 'device_uuid', 'device_name', 'hwid_hash', 'public_key', 'assurance', 'client_version', 'first_activated_at', 'last_seen_at', 'last_ip', 'status'];

    protected $hidden = ['public_key', 'hwid_hash'];

    protected function casts(): array
    {
        return ['first_activated_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(LicenseSession::class, 'device_id');
    }
}
