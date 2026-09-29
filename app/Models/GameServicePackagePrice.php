<?php

namespace App\Models;

use Database\Factories\GameServicePackagePriceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameServicePackagePrice extends Model
{
    /** @use HasFactory<GameServicePackagePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'game_service_package_id', 'label', 'code', 'price', 'collaborator_price', 'original_price', 'quantity_enabled', 'min_quantity', 'max_quantity', 'status', 'sort_order',
    ];

    protected $attributes = ['collaborator_price' => 0, 'quantity_enabled' => false, 'min_quantity' => 1, 'max_quantity' => 1, 'status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'price' => 'integer', 'collaborator_price' => 'integer', 'original_price' => 'integer', 'quantity_enabled' => 'boolean',
            'min_quantity' => 'integer', 'max_quantity' => 'integer', 'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(GameServicePackage::class, 'game_service_package_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GameServiceOrder::class);
    }
}
