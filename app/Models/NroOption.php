<?php

namespace App\Models;

class NroOption extends NroGameDataRecord
{
    protected $table = 'nro_options';

    protected $fillable = ['kind', 'id_option', 'name', 'type', 'color', 'source', 'content_hash', 'icon', 'image'];
}
