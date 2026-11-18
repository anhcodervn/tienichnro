<?php

namespace App\Models;

use Database\Factories\ManagedServerFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagedServer extends Model
{
    /** @use HasFactory<ManagedServerFactory> */
    use HasFactory;

    public const TYPES = ['carot', 'website', 'game'];

    protected $fillable = ['type', 'server_id', 'name', 'is_active', 'sort_order'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return Collection<int, static> */
    public static function activeOptions(string $type): Collection
    {
        return static::query()->where('type', $type)->where('is_active', true)
            ->select(['id', 'server_id as server_code', 'name'])->orderBy('sort_order')->orderBy('id')->get();
    }
}
