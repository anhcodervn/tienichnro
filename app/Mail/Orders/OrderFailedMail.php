<?php

namespace App\Mail\Orders;

class OrderFailedMail extends OrderMail
{
    protected function subjectText(): string
    {
        return 'Đơn nạp game cần hỗ trợ';
    }

    protected function heading(): string
    {
        return 'Đơn hàng chưa thể hoàn tất';
    }

    protected function messageText(): string
    {
        return 'Đơn hàng gặp sự cố khi xử lý. Đội ngũ hỗ trợ sẽ kiểm tra và liên hệ với bạn.';
    }
}
