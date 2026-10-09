<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeNotify extends Model
{
    protected $table = 'type_notifies';

    protected $fillable = ['code', 'name'];
}
