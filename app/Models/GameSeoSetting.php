<?php

namespace App\Models;

use Database\Factories\GameSeoSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSeoSetting extends Model
{
    /** @use HasFactory<GameSeoSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'game_id', 'meta_title', 'meta_description', 'meta_keywords', 'h1', 'article_title', 'content',
        'og_image', 'og_image_alt', 'canonical_url', 'robots', 'faqs', 'is_published',
        'breadcrumb_schema', 'webpage_schema',
    ];

    protected $attributes = [
        'robots' => 'index,follow',
        'is_published' => false,
        'breadcrumb_schema' => true,
        'webpage_schema' => true,
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'faqs' => 'array',
            'is_published' => 'boolean',
            'breadcrumb_schema' => 'boolean',
            'webpage_schema' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
