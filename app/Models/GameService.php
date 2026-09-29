<?php

namespace App\Models;

use Database\Factories\GameServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameService extends Model
{
    /** @use HasFactory<GameServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id', 'name', 'slug', 'code', 'description', 'background_image', 'payload_fields', 'seo_content', 'faqs', 'status', 'sort_order',
    ];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    protected function casts(): array
    {
        return ['payload_fields' => 'array', 'seo_content' => 'array', 'faqs' => 'array', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(GameServer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(GameServicePackage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GameServiceOrder::class);
    }
}
