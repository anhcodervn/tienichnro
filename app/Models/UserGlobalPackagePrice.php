<?php

namespace App\Models;

use Database\Factories\UserGlobalPackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGlobalPackagePrice extends Model
{
    /** @use HasFactory<UserGlobalPackagePriceFactory> */
    use HasFactory;

    public const MODE_DISCOUNT = 'discount';

    public const MODE_FIXED = 'fixed';

    protected $fillable = [
        'user_id',
        'global_topup_package_id',
        'pricing_mode',
        'discount_basis_points',
        'fixed_price',
        'minimum_profit',
        'is_active',
    ];

    protected $attributes = [
        'pricing_mode' => self::MODE_DISCOUNT,
        'minimum_profit' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'discount_basis_points' => 'integer',
            'fixed_price' => 'integer',
            'minimum_profit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function globalPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class, 'global_topup_package_id');
    }
}
