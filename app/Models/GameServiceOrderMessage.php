<?php

namespace App\Models;

use Database\Factories\GameServiceOrderMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameServiceOrderMessage extends Model
{
    /** @use HasFactory<GameServiceOrderMessageFactory> */
    use HasFactory;

    public const ROLE_USER = 'user';

    public const ROLE_COLLABORATOR = 'collaborator';

    public const ROLE_ADMIN = 'admin';

    protected $fillable = ['game_service_order_id', 'sender_id', 'sender_role', 'message'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GameServiceOrder::class, 'game_service_order_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
