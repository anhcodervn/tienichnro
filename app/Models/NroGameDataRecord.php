<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class NroGameDataRecord extends Model
{
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
