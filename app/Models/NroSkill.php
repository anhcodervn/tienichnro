<?php

namespace App\Models;

class NroSkill extends NroGameDataRecord
{
    protected $table = 'nro_skills';

    protected $fillable = ['class_id', 'class_name', 'id_skill', 'name', 'max_point', 'mana_use_type', 'type', 'icon_id', 'dam_info', 'description', 'levels', 'source', 'content_hash', 'icon', 'image'];
}
