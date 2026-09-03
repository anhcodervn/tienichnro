<?php

namespace App\Models;

use Database\Factories\TenantPackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantPackagePrice extends Model
{
    /** @use HasFactory<TenantPackagePriceFactory> */
    use HasFactory;

    public const MODE_FIXED = 'fixed';

    public const MODE_MARKUP_AMOUNT = 'markup_amount';

    public const MODE_MARKUP_PERCENTAGE = 'markup_percentage';

    protected $fillable = [
        'tenant_id', 'topup_package_id', 'pricing_mode', 'fixed_price',
        'markup_amount', 'markup_basis_points', 'is_active',
    ];

    protected $attributes = ['pricing_mode' => self::MODE_MARKUP_AMOUNT, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'fixed_price' => 'integer',
            'markup_amount' => 'integer',
            'markup_basis_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }
}
