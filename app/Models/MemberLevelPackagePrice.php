<?php

namespace App\Models;

use Database\Factories\MemberLevelPackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLevelPackagePrice extends Model
{
    /** @use HasFactory<MemberLevelPackagePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'member_level_id', 'topup_package_id', 'pricing_mode', 'discount_basis_points',
        'fixed_price', 'minimum_profit', 'is_active',
    ];

    protected $attributes = ['pricing_mode' => 'discount', 'is_active' => true];

    protected function casts(): array
    {
        return [
            'discount_basis_points' => 'integer',
            'fixed_price' => 'integer',
            'minimum_profit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function memberLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class);
    }

    public function topupPackage(): BelongsTo
    {
        return $this->belongsTo(TopupPackage::class);
    }
}
