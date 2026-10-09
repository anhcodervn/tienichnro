<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Boss extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'game_names', 'respawn_seconds', 'is_active', 'sort_order'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['game_names' => 'array', 'respawn_seconds' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function notifies(): HasMany
    {
        return $this->hasMany(Notify::class);
    }
}
