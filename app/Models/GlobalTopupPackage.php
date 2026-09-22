<?php

namespace App\Models;

use Database\Factories\GlobalTopupPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalTopupPackage extends Model
{
    /** @use HasFactory<GlobalTopupPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id', 'provider_service_codes', 'name', 'code', 'denomination', 'carot_amount',
        'reward_x2_amount', 'reward_x3_amount', 'first_topup_reward_amount', 'provider_price',
        'price', 'original_price', 'description', 'bonus_text', 'status', 'sort_order', 'metadata',
    ];

    protected $attributes = ['provider_price' => 0, 'status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'denomination' => 'integer',
            'carot_amount' => 'integer',
            'reward_x2_amount' => 'integer',
            'reward_x3_amount' => 'integer',
            'first_topup_reward_amount' => 'integer',
            'provider_price' => 'integer',
            'price' => 'integer',
            'original_price' => 'integer',
            'sort_order' => 'integer',
            'provider_service_codes' => 'array',
            'metadata' => 'array',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(TopupProvider::class, 'provider_id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TopupPackage::class);
    }

    public function gameSettings(): HasMany
    {
        return $this->hasMany(GlobalTopupPackageGameSetting::class, 'denomination', 'denomination');
    }

    public function userPrices(): HasMany
    {
        return $this->hasMany(UserGlobalPackagePrice::class);
    }

    public function levelPrices(): HasMany
    {
        return $this->hasMany(MemberLevelGlobalPackagePrice::class);
    }

    public function affiliateRates(): HasMany
    {
        return $this->hasMany(AffiliateGlobalPackageRate::class);
    }

    public function providerPrices(): HasMany
    {
        return $this->hasMany(TopupProviderPrice::class, 'global_topup_package_id');
    }
}
