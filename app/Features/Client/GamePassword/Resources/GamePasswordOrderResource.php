<?php

namespace App\Features\Client\GamePassword\Resources;

use App\Models\GamePasswordOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamePasswordOrderResource extends JsonResource
{
    public const STATUSES = ['pending' => 'Chờ xử lý', 'processing' => 'Đang đổi mật khẩu', 'success' => 'Hoàn tất', 'error' => 'Lỗi', 'needs_review' => 'Cần kiểm tra'];

    public const PROGRESS = ['queued' => 'Đã nhận yêu cầu', 'login' => 'Đang đăng nhập tài khoản', 'change' => 'Đang mở trang đổi mật khẩu', 'submitting' => 'Đang gửi mật khẩu mới', 'verify' => 'Đang xác nhận mật khẩu mới', 'success' => 'Đã xác nhận đăng nhập bằng mật khẩu mới', 'error' => 'Xử lý không thành công', 'needs_review' => 'Chưa xác nhận được kết quả. Không tự chạy lại.'];

    public static function errorMessage(?string $code): ?string
    {
        return match ($code) {
            null => null,
            'USER_SOLVER_NOT_CONFIGURED' => 'Nhập API Key và API Secret giaicaptcha.vn của bạn rồi bấm đổi lại.',
            'USER_SOLVER_AUTH_FAILED' => 'API Key hoặc API Secret giaicaptcha.vn không hợp lệ. Cập nhật khóa rồi đổi lại.',
            'LOGIN_FAILED' => 'Không đăng nhập được bằng mật khẩu cũ. Kiểm tra tài khoản và server.',
            'VERIFY_FAILED' => 'Chưa xác nhận được mật khẩu mới. Chạy lại miễn phí để hệ thống kiểm tra trước khi đổi.',
            'FORM_CHANGED' => 'Không đọc được biểu mẫu từ website game.',
            'SOLVER_FAILED', 'SOLVER_NOT_CONFIGURED' => 'Dịch vụ captcha chưa sẵn sàng.',
            'TOOL_UNAVAILABLE' => 'Công cụ hoặc tài khoản người dùng đang tạm ngưng.',
            'ADMIN_REFUND' => 'Quản trị viên đã kiểm tra và hoàn phí.',
            default => 'Kết nối xử lý bị gián đoạn. Xem trạng thái đơn trước khi gửi lại.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'has_saved_credentials' => $this->resource->hasSavedCredentials(), 'can_retry' => $this->resource->canRetry(), 'queued_at' => $this->queued_at?->toISOString(), 'attempt_version' => $this->attempt_version, 'username' => $this->username, 'server_code' => $this->server_code,
            'member' => $this->whenLoaded('user', fn () => $this->user?->username),
            'status' => $this->status, 'status_label' => self::STATUSES[$this->status],
            'progress' => self::PROGRESS[$this->progress_code] ?? GamePasswordOrder::LOG_MESSAGES[$this->progress_code] ?? '', 'error_code' => $this->error_code,
            'error_message' => self::errorMessage($this->error_code), 'charged_amount' => $this->charged_amount,
            'refunded_at' => $this->refunded_at?->toISOString(), 'created_at' => $this->created_at?->toISOString(), 'completed_at' => $this->completed_at?->toISOString()];
    }
}
