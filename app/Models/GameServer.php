<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameServer extends Model
{
    use HasFactory;

    protected $fillable = ['game_id', 'name', 'code', 'status', 'sort_order', 'metadata'];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(TopupPackage::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'game_server_id');
    }
}
