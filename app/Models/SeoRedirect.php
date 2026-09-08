<?php

namespace App\Models;

use Database\Factories\SeoRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    /** @use HasFactory<SeoRedirectFactory> */
    use HasFactory;

    protected $fillable = [
        'from_path',
        'to_path',
        'status_code',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
        ];
    }
}
