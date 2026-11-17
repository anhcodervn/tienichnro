<?php

namespace App\Models;

use Database\Factories\GameImageAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameImageAccount extends Model
{
    public const STATUS_LABELS = ['pending' => 'Chờ ghép', 'processing' => 'Đang chụp ảnh', 'success' => 'Hoàn tất', 'error' => 'Lỗi', 'paused' => 'Tạm dừng', 'cancelled' => 'Đã huỷ'];

    /** @use HasFactory<GameImageAccountFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'username', 'password', 'server', 'layout'];

    protected $hidden = ['password', 'share_token', 'shared_layout', 'share_expires_at', 'preview_path', 'capture_directory', 'capture_files', 'capture_claim_hash', 'capture_public_key'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'password' => 'encrypted', 'layout' => 'array', 'shared_layout' => 'array', 'share_expires_at' => 'immutable_datetime', 'capture_files' => 'array',
            'capture_version' => 'integer', 'charged_amount' => 'integer', 'refunded_amount' => 'integer', 'processing_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime'];
    }

    public function captureLogs(): HasMany
    {
        return $this->hasMany(GameImageCaptureLog::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
