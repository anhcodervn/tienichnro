<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NroServer extends Model
{
    use HasFactory;

    protected $table = 'servers';

    protected $fillable = ['server_code', 'code', 'name', 'is_active', 'sort_order'];

    protected $attributes = ['connection' => false];

    protected function casts(): array
    {
        return ['server_code' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer', 'connection' => 'boolean', 'last_ping_at' => 'immutable_datetime'];
    }

    public static function heartbeatTimeoutSeconds(): int
    {
        return max(1, (int) config('services.nro_bot.heartbeat_interval_seconds', 30))
            * max(1, (int) config('services.nro_bot.missed_heartbeats', 5));
    }

    /** @return Attribute<bool, never> */
    protected function connection(): Attribute
    {
        return Attribute::make(get: fn (mixed $value): bool => (bool) $value
            && $this->last_ping_at !== null
            && $this->last_ping_at->greaterThan(now()->subSeconds(self::heartbeatTimeoutSeconds())));
    }
}
