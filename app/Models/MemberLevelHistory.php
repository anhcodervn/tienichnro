<?php

namespace App\Models;

use Database\Factories\MemberLevelHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLevelHistory extends Model
{
    /** @use HasFactory<MemberLevelHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'from_level_id', 'to_level_id', 'actor_id', 'type', 'reason',
        'lifetime_completed_amount', 'metadata',
    ];

    protected function casts(): array
    {
        return ['lifetime_completed_amount' => 'integer', 'metadata' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'from_level_id');
    }

    public function toLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'to_level_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
