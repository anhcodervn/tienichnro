<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NroServer extends Model
{
    use HasFactory;

    protected $table = 'servers';

    protected $fillable = ['server_code', 'code', 'name', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['server_code' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
