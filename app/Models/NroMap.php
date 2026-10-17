<?php

namespace App\Models;

class NroMap extends NroGameDataRecord
{
    protected $table = 'nro_maps';

    protected $fillable = ['id_map', 'name', 'source', 'content_hash', 'icon', 'image'];
}
