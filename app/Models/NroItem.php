<?php

namespace App\Models;

class NroItem extends NroGameDataRecord
{
    protected $table = 'nro_items';

    protected $fillable = ['id_item', 'type', 'gender', 'name', 'description', 'level', 'str_require', 'icon_id', 'part', 'is_up_to_up', 'source', 'content_hash', 'icon', 'image'];
}
