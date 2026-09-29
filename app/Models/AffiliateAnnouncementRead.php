<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateAnnouncementRead extends Model
{
    protected $fillable = ['affiliate_announcement_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(AffiliateAnnouncement::class, 'affiliate_announcement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
