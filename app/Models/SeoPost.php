<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoPost extends Model
{
    protected $attributes = [
        'type' => 'knowledge',
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
        'canonical_url',
        'robots',
        'focus_keyword',
        'cover_alt',
        'article_schema',
        'breadcrumb_schema',
        'status',
        'published_at',
        'scheduled_at',
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
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SeoCategory::class, 'seo_category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'service_id');
    }
}
