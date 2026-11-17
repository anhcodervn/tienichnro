<?php

namespace App\Features\Client\GameVerification\Resources;

use App\Features\Client\GamePassword\Resources\GamePasswordOrderResource;
use App\Models\GameVerificationOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameVerificationOrderResource extends JsonResource
{
    public const STATUSES = ['pending' => 'Chờ xử lý', 'processing' => 'Đang lấy cú pháp', 'success' => 'Đã lấy cú pháp', 'error' => 'Lỗi'];

    public const PROGRESS = GameVerificationOrder::LOG_MESSAGES;

    public static function errorMessage(?string $code): ?string
    {
        if ($code === 'LOGIN_FAILED') {
            return 'Không đăng nhập được. Kiểm tra tài khoản, mật khẩu và server.';
        }
        if ($code === 'SYNTAX_MISSING') {
            return 'Không tìm thấy cú pháp xác minh. Tài khoản có thể đã xác minh hoặc biểu mẫu đã thay đổi.';
        }

        return $code === null ? null : GamePasswordOrderResource::errorMessage($code);
    }

    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'attempt_version' => $this->attempt_version, 'can_retry' => $this->resource->canRetry(), 'has_saved_credentials' => $this->resource->hasSavedCredentials(),
            'username' => $this->username, 'server_code' => $this->server_code, 'status' => $this->status,
            'status_label' => self::STATUSES[$this->status], 'progress' => self::PROGRESS[$this->progress_code] ?? '',
            'error_code' => $this->error_code, 'error_message' => self::errorMessage($this->error_code),
            'charged_amount' => $this->charged_amount, 'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'member' => $this->whenLoaded('user', fn () => $this->user?->username)];
    }
}
