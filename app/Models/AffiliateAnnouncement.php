<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateAnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateAnnouncement extends Model
{
    /** @use HasFactory<AffiliateAnnouncementFactory> */
    use BelongsToTenant, HasFactory;

    public const AUDIENCE_AFFILIATE = 'affiliate';

    public const AUDIENCE_COLLABORATOR = 'collaborator';

    public const AUDIENCES = [self::AUDIENCE_AFFILIATE, self::AUDIENCE_COLLABORATOR];

    protected $fillable = [
        'tenant_id', 'admin_id', 'audience', 'title', 'content', 'is_pinned', 'is_published', 'published_at',
    ];

    protected $attributes = [
        'audience' => self::AUDIENCE_AFFILIATE,
        'is_pinned' => false,
        'is_published' => true,
    ];

    protected function casts(): array
    {
        return ['content' => 'array', 'is_pinned' => 'boolean', 'is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AffiliateAnnouncementRead::class);
    }
}
