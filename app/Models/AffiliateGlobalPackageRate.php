<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateGlobalPackageRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateGlobalPackageRate extends Model
{
    /** @use HasFactory<AffiliateGlobalPackageRateFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'global_topup_package_id', 'commission_type', 'fixed_amount', 'percentage_basis_points', 'is_active',
    ];

    protected $attributes = ['commission_type' => AffiliatePackageRate::TYPE_FIXED, 'is_active' => true];

    protected function casts(): array
    {
        return ['fixed_amount' => 'integer', 'percentage_basis_points' => 'integer', 'is_active' => 'boolean'];
    }

    public function globalTopupPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class);
    }
}
