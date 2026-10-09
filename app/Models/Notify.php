<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notify extends Model
{
    protected $table = 'notifies';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['server_id', 'code_id', 'is_boss', 'boss_id', 'boss_name', 'char_name', 'content', 'death_content', 'map_name', 'map_id', 'zone', 'zone_name', 'time_start', 'expires_at', 'death_time', 'killed_by', 'respawn_at', 'metadata'];

    protected function casts(): array
    {
        return ['is_boss' => 'boolean', 'time_start' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'death_time' => 'immutable_datetime', 'respawn_at' => 'immutable_datetime', 'metadata' => 'array', 'zone' => 'integer'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(NroServer::class, 'server_id');
    }

    public function boss(): BelongsTo
    {
        return $this->belongsTo(Boss::class)->withTrashed();
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(CodeNotify::class, 'code_id')->withTrashed();
    }

    public function scopeLiving(Builder $query): Builder
    {
        return $query->where(fn (Builder $bosses): Builder => $bosses->whereNotNull('boss_id')->orWhereNotNull('boss_name'))->whereNull('death_time');
    }

    public function scopeRespawning(Builder $query): Builder
    {
        return $query->bossHistory()->whereNotNull('death_time')->where('respawn_at', '>', now());
    }

    public function scopeBossHistory(Builder $query): Builder
    {
        return $query->where(fn (Builder $bosses): Builder => $bosses->where('is_boss', true)->orWhereNotNull('boss_id')->orWhereNotNull('boss_name'));
    }

    public function isBoss(): bool
    {
        return $this->is_boss || $this->boss_id !== null || $this->boss_name !== null;
    }
}
