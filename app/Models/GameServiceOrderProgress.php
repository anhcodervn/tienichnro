<?php

namespace App\Models;

use Database\Factories\GameServiceOrderProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GameServiceOrderProgress extends Model
{
    /** @use HasFactory<GameServiceOrderProgressFactory> */
    use HasFactory;

    public const TYPE_PROGRESS = 'progress';

    public const TYPE_COMPLETION = 'completion';

    protected $table = 'game_service_order_progress';

    protected $fillable = [
        'game_service_order_id',
        'user_id',
        'type',
        'description',
        'image_path',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GameServiceOrder::class, 'game_service_order_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function message(): HasOne
    {
        return $this->hasOne(GameServiceOrderMessage::class, 'game_service_order_progress_id');
    }
}
