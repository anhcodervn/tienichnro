<?php

namespace App\Models;

use Database\Factories\GameImageCaptureLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameImageCaptureLog extends Model
{
    /** @use HasFactory<GameImageCaptureLogFactory> */
    use HasFactory;

    protected $fillable = ['account_id', 'capture_version', 'client_log_id', 'level', 'step', 'message', 'occurred_at'];

    protected function casts(): array
    {
        return ['account_id' => 'integer', 'capture_version' => 'integer', 'occurred_at' => 'immutable_datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GameImageAccount::class, 'account_id');
    }
}
