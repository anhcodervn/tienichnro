<?php

namespace App\Models;

use Database\Factories\ServicePackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePackage extends Model
{
    /** @use HasFactory<ServicePackageFactory> */
    use HasFactory;

    protected $fillable = ['service_code', 'name', 'description', 'price', 'billing_type', 'usage_limit', 'duration_days', 'is_active', 'sort_order', 'notification_mode'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'usage_limit' => 'integer', 'duration_days' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'service_code', 'code');
    }
}
