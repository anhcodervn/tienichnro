<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliatePackageRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliatePackageRate extends Model
{
    /** @use HasFactory<AffiliatePackageRateFactory> */
    use BelongsToTenant, HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    protected $fillable = [
        'tenant_id', 'topup_package_id', 'commission_type', 'fixed_amount', 'percentage_basis_points', 'is_active',
    ];

    protected $attributes = ['commission_type' => self::TYPE_FIXED, 'is_active' => true];

    protected function casts(): array
    {
        return ['fixed_amount' => 'integer', 'percentage_basis_points' => 'integer', 'is_active' => 'boolean'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }
}
