<?php

namespace App\Models;

use Database\Factories\ZaloReceiveNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZaloReceiveNotification extends Model
{
    /** @use HasFactory<ZaloReceiveNotificationFactory> */
    use HasFactory;

    protected $fillable = ['box_zalo_id', 'zalo_id', 'char_name', 'char_server', 'type_receive'];

    protected function casts(): array
    {
        return ['box_zalo_id' => 'string', 'zalo_id' => 'string', 'char_server' => 'integer'];
    }

    public function scopeForCharacter(Builder $query, string $charName, int $serverCode): Builder
    {
        return $query->where('char_name', $charName)->where('char_server', $serverCode);
    }

    /** @return list<string> */
    public function notificationTypes(): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (string $type): string => strtoupper(trim($type)), explode(',', $this->type_receive)),
            fn (string $type): bool => $type !== '',
        )));
    }

    public function receivesType(string $type): bool
    {
        return in_array(strtoupper(trim($type)), $this->notificationTypes(), true);
    }
}
