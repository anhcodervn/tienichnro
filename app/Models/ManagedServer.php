<?php

namespace App\Models;

use Database\Factories\ManagedServerFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagedServer extends Model
{
    /** @use HasFactory<ManagedServerFactory> */
    use HasFactory;

    public const TYPES = ['napcarot', 'web', 'game'];

    protected $table = 'servers';

    protected $fillable = ['type', 'server_id', 'server_code', 'code', 'name', 'is_active', 'sort_order'];

    protected $appends = ['server_id'];

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'server_code' => 'integer'];
    }

    protected function serverId(): Attribute
    {
        return Attribute::make(get: fn (mixed $value, array $attributes): string => (string) ($attributes['server_code'] ?? ''),
            set: fn (mixed $value): array => ['server_code' => (int) $value]);
    }

    /** @return Collection<int, static> */
    public static function activeOptions(string $type): Collection
    {
        return static::query()->where('type', $type)->where('is_active', true)
            ->select(['id', 'server_code', 'name'])->orderBy('sort_order')->orderBy('id')->get();
    }
}
