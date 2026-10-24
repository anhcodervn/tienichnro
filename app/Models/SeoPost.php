<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoPost extends Model
{
    protected $attributes = [
        'type' => 'knowledge',
        'language' => 'vi',
        'lock_version' => 0,
    ];

    protected $fillable = [
        'seo_category_id',
        'type',
        'service_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'faq',
        'cover_image',
        'seo_title',
        'seo_description',
        'meta_keywords',
        'canonical_url',
        'robots',
        'focus_keyword',
        'cover_alt',
        'article_schema',
        'breadcrumb_schema',
        'status',
        'published_at',
        'scheduled_at',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'faq' => 'array',
            'article_schema' => 'boolean',
            'breadcrumb_schema' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SeoCategory::class, 'seo_category_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SeoPostRevision::class);
    }
}
