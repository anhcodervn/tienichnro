<?php

namespace App\Models;

use Database\Factories\GlobalTopupPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalTopupPackage extends Model
{
    /** @use HasFactory<GlobalTopupPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'denomination', 'price', 'original_price', 'description', 'status', 'sort_order',
    ];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'denomination' => 'integer',
            'price' => 'integer',
            'original_price' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TopupPackage::class);
    }

    public function levelPrices(): HasMany
    {
        return $this->hasMany(MemberLevelGlobalPackagePrice::class);
    }
}
