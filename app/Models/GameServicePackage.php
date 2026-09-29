<?php

namespace App\Models;

use Database\Factories\GameServicePackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameServicePackage extends Model
{
    /** @use HasFactory<GameServicePackageFactory> */
    use HasFactory;

    protected $fillable = ['game_service_id', 'name', 'code', 'description', 'status', 'sort_order'];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GameService::class, 'game_service_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(GameServicePackagePrice::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GameServiceOrder::class);
    }
}
