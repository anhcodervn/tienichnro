<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseSession extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'license_sessions';

    protected $fillable = ['id', 'license_id', 'device_id', 'token_hash', 'generation', 'status', 'started_at', 'last_heartbeat_at', 'lease_expires_at', 'revoked_at', 'revocation_reason'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_heartbeat_at' => 'datetime', 'lease_expires_at' => 'datetime', 'revoked_at' => 'datetime', 'generation' => 'integer'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(LicenseDevice::class, 'device_id');
    }
}
