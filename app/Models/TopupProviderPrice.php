<?php

namespace App\Models;

use Database\Factories\TopupProviderPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopupProviderPrice extends Model
{
    /** @use HasFactory<TopupProviderPriceFactory> */
    use HasFactory;

    protected $fillable = ['topup_provider_id', 'topup_package_id', 'global_topup_package_id', 'price', 'available', 'synced_at'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'available' => 'boolean', 'synced_at' => 'datetime'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TopupProvider::class, 'topup_provider_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }

    public function globalPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class, 'global_topup_package_id');
    }
}
