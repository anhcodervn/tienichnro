<?php

namespace App\Models;

use App\Features\NroGameData\Services\GameDataReadCacheService;
use Illuminate\Database\Eloquent\Model;

abstract class NroGameDataRecord extends Model
{
    protected static function booted(): void
    {
        static::saved(function (NroGameDataRecord $record): void {
            if ($record->wasRecentlyCreated || $record->wasChanged()) {
                app(GameDataReadCacheService::class)->invalidate($record);
            }
        });
        static::deleted(function (NroGameDataRecord $record): void {
            app(GameDataReadCacheService::class)->invalidate($record);
        });
    }

    protected function casts(): array
    {
        return [
            'source' => 'array', 'menu' => 'array', 'levels' => 'array', 'is_up_to_up' => 'boolean',
            ...array_fill_keys([
                'id_map', 'id_npc', 'id_mob', 'id_item', 'id_option', 'id_skill', 'class_id',
                'head_id', 'body_id', 'leg_id', 'type', 'hp', 'range_move', 'speed', 'dart_type',
                'gender', 'level', 'str_require', 'icon_id', 'part', 'color', 'max_point', 'mana_use_type',
            ], 'integer'),
        ];
    }
}
