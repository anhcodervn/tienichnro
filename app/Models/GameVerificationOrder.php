<?php

namespace App\Models;

use Database\Factories\GameVerificationOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameVerificationOrder extends Model
{
    /** @use HasFactory<GameVerificationOrderFactory> */
    use HasFactory;

    public const LOG_MESSAGES = ['queued' => 'Đã nhận yêu cầu, chờ xử lý', 'retry' => 'Đã tạo lượt chạy lại miễn phí',
        'login_page' => 'Đang mở trang đăng nhập', 'login_captcha' => 'Đang giải captcha đăng nhập',
        'login' => 'Đang đăng nhập tài khoản', 'login_success' => 'Đăng nhập thành công',
        'profile' => 'Đang mở trang thông tin cá nhân', 'extract' => 'Đang lấy cú pháp xác minh',
        'success' => 'Đã lấy cú pháp SMS. Chưa xác nhận gửi tin hoặc xác minh thành công.', 'error' => 'Lấy cú pháp không thành công', 'done' => 'Done — kết thúc lượt xử lý'];

    protected $fillable = ['user_id', 'request_id', 'row_index', 'payload_hash', 'username', 'server_code', 'old_password', 'active_key', 'charged_amount'];

    protected $hidden = ['old_password', 'captcha_api_key', 'captcha_api_secret', 'sms_syntax', 'active_key', 'payload_hash'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'server_code' => 'integer', 'attempt_version' => 'integer', 'charged_amount' => 'integer',
            'old_password' => 'encrypted', 'captcha_api_key' => 'encrypted', 'captcha_api_secret' => 'encrypted', 'sms_syntax' => 'encrypted',
            'progress_logs' => 'array', 'queued_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'processing_at' => 'immutable_datetime'];
    }

    public function canRetry(): bool
    {
        $queuedAt = $this->queued_at ?? $this->created_at;

        return $this->status === 'error' || ($this->status === 'pending' && $queuedAt !== null && $queuedAt->getTimestamp() < now()->subMinutes(30)->getTimestamp());
    }

    public function hasSavedCredentials(): bool
    {
        if (array_key_exists('has_saved_credentials', $this->getAttributes())) {
            return (bool) $this->getAttribute('has_saved_credentials');
        }

        return filled($this->getRawOriginal('old_password'));
    }

    public function appendProgressLog(string $code): void
    {
        if (! isset(self::LOG_MESSAGES[$code])) {
            return;
        }
        $logs = $this->progress_logs ?? [];
        $logs[] = ['code' => $code, 'at' => now()->format('H:i:s'), 'attempt' => $this->attempt_version ?? 1];
        $this->progress_logs = array_slice($logs, -100);
    }

    public function progressLogText(): string
    {
        return collect($this->progress_logs ?? [])->filter(fn (array $log): bool => isset(self::LOG_MESSAGES[$log['code'] ?? '']))
            ->map(fn (array $log): string => '['.$log['at'].'] [Lượt '.$log['attempt'].'] '.self::LOG_MESSAGES[$log['code']])->implode("\n");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
