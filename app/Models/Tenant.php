<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'billing_user_id',
        'status',
        'is_main',
        'allow_below_cost',
    ];

    protected $attributes = [
        'status' => 'active',
        'is_main' => false,
        'allow_below_cost' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'allow_below_cost' => 'boolean',
        ];
    }

    public function billingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'billing_user_id')->withoutGlobalScope(TenantScope::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function packagePrices(): HasMany
    {
        return $this->hasMany(TenantPackagePrice::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScope(TenantScope::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->withoutGlobalScope(TenantScope::class);
    }
}
