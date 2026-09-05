<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AffiliateAnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAnnouncement extends Model
{
    /** @use HasFactory<AffiliateAnnouncementFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'admin_id', 'title', 'content', 'is_pinned', 'is_published', 'published_at',
    ];

    protected $attributes = ['is_pinned' => false, 'is_published' => true];

    protected function casts(): array
    {
        return ['content' => 'array', 'is_pinned' => 'boolean', 'is_published' => 'boolean', 'published_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
