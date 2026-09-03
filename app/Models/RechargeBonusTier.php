<?php

namespace App\Models;

use Database\Factories\RechargeBonusTierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RechargeBonusTier extends Model
{
    /** @use HasFactory<RechargeBonusTierFactory> */
    use HasFactory;

    protected $fillable = [
        'minimum_amount',
        'bonus_basis_points',
        'is_active',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'integer',
            'bonus_basis_points' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
