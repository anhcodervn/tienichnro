<?php

namespace App\Models;

use Database\Factories\MemberLevelAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberLevelAccount extends Model
{
    /** @use HasFactory<MemberLevelAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'earned_level_id', 'manual_level_id', 'lifetime_completed_amount',
        'last_qualified_order_at', 'manual_level_expires_at',
    ];

    protected $attributes = ['lifetime_completed_amount' => 0];

    protected function casts(): array
    {
        return [
            'lifetime_completed_amount' => 'integer',
            'last_qualified_order_at' => 'datetime',
            'manual_level_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function earnedLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'earned_level_id');
    }

    public function manualLevel(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'manual_level_id');
    }
}
