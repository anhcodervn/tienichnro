<?php

namespace App\Models;

use Database\Factories\UserGlobalPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGlobalPrice extends Model
{
    /** @use HasFactory<UserGlobalPriceFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'discount_basis_points',
        'minimum_profit',
        'is_active',
    ];

    protected $attributes = [
        'discount_basis_points' => 0,
        'minimum_profit' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'discount_basis_points' => 'integer',
            'minimum_profit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
