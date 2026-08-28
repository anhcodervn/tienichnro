<?php

namespace App\Features\Reporting\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderRecipient;
use App\Models\TopupProvider;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class TopupDiscordReporterService
{
    public function __construct(
        private readonly DiscordReportService $discordReportService,
        private readonly TopupReportService $topupReportService,
    ) {}

    public function orderCreated(Order $order): bool
    {
        return $this->discordReportService->queue(
            channel: 'sales',
            title: 'Đơn nạp game mới',
            details: $this->orderDetails($order),
            dedupeKey: "topup-order:{$order->id}:created",
        );
    }

    public function paymentReceived(Order $order): bool
    {
        return $this->discordReportService->queue(
            channel: 'sales',
            title: 'Đã nhận thanh toán đơn nạp game',
            details: [
                ...$this->orderDetails($order),
                'Thanh toán lúc' => $order->paid_at,
            ],
            dedupeKey: "topup-order:{$order->id}:paid",
        );
    }

    public function refundIssued(Order $order): bool
    {
        return $this->discordReportService->queue(
            channel: 'sales',
            title: 'Đơn nạp game đã hoàn tiền',
            details: [
                ...$this->orderDetails($order),
                'Hoàn tiền lúc' => $order->updated_at,
            ],
            dedupeKey: "topup-order:{$order->id}:refunded",
        );
    }

    public function orderStatusChanged(Order $order): bool
    {
        $status = $order->order_status;

        if (! in_array($status, [OrderStatus::Completed, OrderStatus::Failed, OrderStatus::Cancelled], true)) {
            return false;
        }

        return $this->discordReportService->queue(
            channel: $status === OrderStatus::Failed ? 'provider' : 'sales',
            title: match ($status) {
                OrderStatus::Completed => 'Đơn nạp game hoàn thành',
                OrderStatus::Failed => 'Đơn nạp game xử lý thất bại',
                OrderStatus::Cancelled => 'Đơn nạp game đã hủy',
                default => 'Trạng thái đơn nạp game thay đổi',
            },
            details: [
                ...$this->orderDetails($order),
                'Trạng thái xử lý' => $status->value,
                'Cập nhật lúc' => $order->updated_at,
            ],
            dedupeKey: "topup-order:{$order->id}:status:{$status->value}",
        );
    }

    public function recipientNeedsAttention(OrderRecipient $recipient): bool
    {
        $recipient->loadMissing('order:id,code,game_id,package_name,quantity,total_amount,payment_method,payment_status,order_status,user_id');

        return $this->discordReportService->queue(
            channel: 'provider',
            title: 'Lượt nạp cần kiểm tra thủ công',
            details: [
                'Mã đơn' => $recipient->order->code,
                'Vị trí người nhận' => $recipient->position,
                'Số lượt' => $recipient->quantity,
                'Trạng thái' => $recipient->status,
                'Số lần kiểm tra' => $recipient->status_check_attempts,
                'Yêu cầu xử lý' => 'Đối soát thủ công trong trang quản trị',
            ],
            dedupeKey: "topup-recipient:{$recipient->id}:attention:{$recipient->status}:{$recipient->status_check_attempts}",
        );
    }

    public function providerBalanceInsufficient(
        Order $order,
        TopupProvider $provider,
        int $providerBalance,
        int $requiredBalance,
        string $currency,
    ): bool {
        return $this->discordReportService->queue(
            channel: 'provider',
            title: 'Provider không đủ số dư - đơn chờ xử lý thủ công',
            details: [
                'Mã đơn' => $order->code,
                'Nhà cung cấp' => $provider->name,
                'Số dư hiện tại' => $this->formatProviderBalance($providerBalance, $currency),
                'Chi phí cần thiết' => $this->formatProviderBalance($requiredBalance, $currency),
                'Số tiền còn thiếu' => $this->formatProviderBalance($requiredBalance - $providerBalance, $currency),
                'Trạng thái xử lý' => 'Không gửi API provider; chờ admin xử lý thủ công',
            ],
            dedupeKey: "topup-order:{$order->id}:provider-balance-insufficient",
        );
    }

    public function paymentGatewayFallback(Order $order): bool
    {
        return $this->discordReportService->queue(
            channel: 'alerts',
            title: 'Cổng thanh toán tự động tạm thời không khả dụng',
            details: [
                'Mã đơn' => $order->code,
                'User ID' => $order->user_id ?? 'guest',
                'Số tiền' => $this->formatMoney((int) $order->total_amount),
                'Xử lý dự phòng' => 'Đã chuyển sang thông tin chuyển khoản nội bộ',
            ],
            dedupeKey: "topup-order:{$order->id}:payment-gateway-fallback",
        );
    }

    public function reorderSucceeded(
        Order $order,
        int $attempt,
        int $adminId,
        int $balanceBefore,
        int $balanceAfter,
        string $currency,
    ): bool {
        $order->loadMissing('provider:id,name');

        return $this->discordReportService->queue(
            channel: 'provider',
            title: 'Reorder đơn nạp game thành công',
            details: [
                'Mã đơn' => $order->code,
                'Nhà cung cấp' => $order->provider?->name ?? 'Không xác định',
                'Lần reorder' => $attempt,
                'Admin ID' => $adminId,
                'Số dư trước' => $this->formatProviderBalance($balanceBefore, $currency),
                'Số dư sau' => $this->formatProviderBalance($balanceAfter, $currency),
                'Biến động' => $this->formatProviderBalance($balanceAfter - $balanceBefore, $currency, true),
                'Hoàn tất lúc' => $order->completed_at,
            ],
            dedupeKey: "topup-order:{$order->id}:reorder:{$attempt}:completed",
        );
    }

    public function dailySummary(CarbonInterface $date): bool
    {
        $day = CarbonImmutable::instance($date);
        $from = $day->startOfDay();
        $to = $day->endOfDay();
        $summary = $this->topupReportService->successfulSummary($from, $to);

        return $this->discordReportService->queue(
            channel: 'sales',
            title: 'Báo cáo topup thành công ngày '.$day->format('d/m/Y'),
            details: [
                'Đơn thành công' => $summary['successful_orders'],
                'Lượt nạp thành công' => $summary['successful_units'],
                'Doanh thu thành công' => $this->formatMoney($summary['revenue']),
                'Giá trị trung bình' => $this->formatMoney($summary['average_order_value']),
                'Khoảng báo cáo' => $from->format('d/m/Y H:i').' - '.$to->format('d/m/Y H:i'),
            ],
            dedupeKey: 'topup-daily:'.$day->format('Y-m-d'),
        );
    }

    /** @return array<string, mixed> */
    private function orderDetails(Order $order): array
    {
        $order->loadMissing('game:id,name');

        return [
            'Mã đơn' => $order->code,
            'Khách hàng' => $order->user_id === null ? 'guest' : 'User #'.$order->user_id,
            'Game' => $order->game?->name ?? 'Không xác định',
            'Gói nạp' => $order->package_name,
            'Số lượng' => $order->quantity,
            'Số tiền' => $this->formatMoney((int) $order->total_amount),
            'Phương thức' => $order->payment_method->value,
            'Thanh toán' => $order->payment_status->value,
        ];
    }

    private function formatMoney(int $amount): string
    {
        return number_format($amount, 0, ',', '.').'đ';
    }

    private function formatProviderBalance(int $amount, string $currency, bool $withSign = false): string
    {
        $sign = $withSign && $amount > 0 ? '+' : '';

        return $sign.number_format($amount, 0, ',', '.').' '.strtoupper($currency);
    }
}
