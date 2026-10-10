<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicensePlan extends Model
{
    use HasFactory;

    protected $table = 'license_plans';

    protected $fillable = ['product_id', 'name', 'duration_days', 'price', 'max_active_devices', 'transfer_cooldown', 'is_active'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'duration_days' => 'integer', 'price' => 'integer', 'transfer_cooldown' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LicenseProduct::class, 'product_id');
    }
}
