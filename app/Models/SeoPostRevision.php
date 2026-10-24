<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoPostRevision extends Model
{
    protected $fillable = ['seo_post_id', 'snapshot', 'published_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'published_at' => 'datetime'];
    }
}
