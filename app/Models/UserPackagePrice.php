<?php

namespace App\Models;

use Database\Factories\UserPackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPackagePrice extends Model
{
    /** @use HasFactory<UserPackagePriceFactory> */
    use HasFactory;

    public const MODE_DISCOUNT = 'discount';

    public const MODE_FIXED = 'fixed';

    protected $fillable = [
        'user_id',
        'topup_package_id',
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

    public function package(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class, 'topup_package_id');
    }
}
