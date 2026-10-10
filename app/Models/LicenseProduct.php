<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LicenseProduct extends Model
{
    use HasFactory;

    protected $table = 'license_products';

    protected $fillable = ['name', 'product_code', 'description', 'minimum_version', 'is_active', 'heartbeat_interval', 'lease_duration', 'transfer_cooldown', 'max_active_devices', 'offline_grace'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'heartbeat_interval' => 'integer', 'lease_duration' => 'integer', 'transfer_cooldown' => 'integer'];
    }

    public function plans(): HasMany
    {
        return $this->hasMany(LicensePlan::class, 'product_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class, 'product_id');
    }
}
