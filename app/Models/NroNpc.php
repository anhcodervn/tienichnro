<?php

namespace App\Models;

class NroNpc extends NroGameDataRecord
{
    protected $table = 'nro_npcs';

    protected $fillable = ['id_npc', 'name', 'head_id', 'body_id', 'leg_id', 'menu', 'source', 'content_hash', 'icon', 'image'];
}
