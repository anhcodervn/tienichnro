<?php

namespace App\Models;

class NroMob extends NroGameDataRecord
{
    protected $table = 'nro_mobs';

    protected $fillable = ['id_mob', 'type', 'name', 'hp', 'range_move', 'speed', 'dart_type', 'source', 'content_hash', 'icon', 'image'];
}
