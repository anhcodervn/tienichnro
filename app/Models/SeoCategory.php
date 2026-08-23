<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoCategory extends Model
{
    public const RESERVED_SLUGS = [
        'admin',
        'api',
        'auth',
        'bai-viet',
        'dat-lai-mat-khau',
        'don-hang',
        'email',
        'nap-game',
        'nap-tien',
        'tai-khoan',
        'tin-tuc',
        'verify-email',
    ];

    protected $fillable = [
        'name',
        'slug',
        'seo_title',
        'seo_description',
        'robots',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SeoPost::class);
    }
}
