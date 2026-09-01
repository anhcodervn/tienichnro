<?php

namespace App\Models;

use Database\Factories\MemberLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberLevel extends Model
{
    /** @use HasFactory<MemberLevelFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'rank', 'lifetime_threshold', 'maintenance_amount', 'maintenance_days',
        'default_discount_bps', 'minimum_profit', 'color', 'icon', 'status', 'sort_order',
    ];

    protected $attributes = [
        'lifetime_threshold' => 0,
        'maintenance_amount' => 0,
        'maintenance_days' => 31,
        'default_discount_bps' => 0,
        'minimum_profit' => 0,
        'color' => '#64748b',
        'icon' => 'crown',
        'status' => 'active',
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'lifetime_threshold' => 'integer',
            'maintenance_amount' => 'integer',
            'maintenance_days' => 'integer',
            'default_discount_bps' => 'integer',
            'minimum_profit' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function packagePrices(): HasMany
    {
        return $this->hasMany(MemberLevelPackagePrice::class);
    }

    public function globalPackagePrices(): HasMany
    {
        return $this->hasMany(MemberLevelGlobalPackagePrice::class);
    }

    public function earnedAccounts(): HasMany
    {
        return $this->hasMany(MemberLevelAccount::class, 'earned_level_id');
    }
}
