<?php

namespace App\Models;

use Database\Factories\GamePasswordOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamePasswordOrder extends Model
{
    public const LOG_MESSAGES = [
        'queued' => 'Đã nhận yêu cầu, chờ xử lý',
        'retry' => 'Đã tạo lượt chạy lại miễn phí',
        'home' => 'Đang mở trang chủ',
        'login_page' => 'Đang mở trang đăng nhập',
        'login_captcha' => 'Đang giải captcha đăng nhập',
        'login' => 'Đang đăng nhập bằng mật khẩu cũ',
        'login_success' => 'Đăng nhập thành công',
        'login_failed' => 'Đăng nhập thất bại bằng mật khẩu cũ',
        'old_check' => 'Đang kiểm tra lại mật khẩu cũ đã lưu',
        'change' => 'Đang tới trang đổi mật khẩu',
        'change_captcha' => 'Đang giải captcha đổi mật khẩu',
        'submitting' => 'Đang gửi yêu cầu đổi mật khẩu',
        'verify' => 'Đang kiểm tra đăng nhập bằng mật khẩu mới',
        'verify_success' => 'Đăng nhập bằng mật khẩu mới thành công',
        'verify_failed' => 'Đăng nhập bằng mật khẩu mới thất bại',
        'success' => 'Đổi mật khẩu thành công, đã xác nhận mật khẩu mới',
        'error' => 'Đổi mật khẩu thất bại hoặc chưa xác nhận được kết quả',
        'needs_review' => 'Cần kiểm tra lại kết quả đổi mật khẩu',
        'done' => 'Done — kết thúc lượt xử lý',
    ];

    /** @use HasFactory<GamePasswordOrderFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'request_id', 'row_index', 'payload_hash', 'username', 'server_code', 'old_password', 'new_password', 'active_key', 'charged_amount'];

    protected $hidden = ['old_password', 'new_password', 'captcha_api_key', 'captcha_api_secret', 'payload_hash', 'active_key'];

    protected function casts(): array
    {
        return ['captcha_api_key' => 'encrypted', 'captcha_api_secret' => 'encrypted', 'progress_logs' => 'array', 'attempt_version' => 'integer', 'verify_before_change' => 'boolean', 'user_id' => 'integer', 'server_code' => 'integer', 'row_index' => 'integer', 'old_password' => 'encrypted', 'new_password' => 'encrypted', 'charged_amount' => 'integer',
            'queued_at' => 'immutable_datetime', 'processing_at' => 'immutable_datetime', 'change_submitted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'refunded_at' => 'immutable_datetime'];
    }

    public function appendProgressLog(string $code): void
    {
        if (! isset(self::LOG_MESSAGES[$code])) {
            return;
        }
        $logs = $this->progress_logs ?? [];
        $logs[] = ['code' => $code, 'at' => now()->format('H:i:s'), 'attempt' => $this->attempt_version ?? 1];
        $this->setAttribute('progress_logs', array_slice($logs, -100));
    }

    public function progressLogText(): string
    {
        return collect($this->progress_logs ?? [])->filter(fn (array $log): bool => isset(self::LOG_MESSAGES[$log['code'] ?? '']))
            ->map(fn (array $log): string => '['.$log['at'].'] [Lượt '.$log['attempt'].'] '.self::LOG_MESSAGES[$log['code']])->implode("\n");
    }

    public function canRetry(): bool
    {
        $queuedAt = $this->queued_at ?? $this->created_at;

        return $this->status === 'error' || ($this->status === 'pending' && $queuedAt !== null && $queuedAt->getTimestamp() < now()->subMinutes(30)->getTimestamp());
    }

    public function hasSavedCredentials(): bool
    {
        $attributes = $this->getAttributes();
        if (array_key_exists('has_saved_credentials', $attributes)) {
            return (bool) $attributes['has_saved_credentials'];
        }

        return filled($this->getRawOriginal('old_password')) && filled($this->getRawOriginal('new_password'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
