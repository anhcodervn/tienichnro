<?php

namespace App\Mail\Orders;

class OrderCompletedMail extends OrderMail
{
    protected function subjectText(): string
    {
        return 'Đơn nạp game hoàn thành';
    }

    protected function heading(): string
    {
        return 'Nạp game hoàn thành';
    }

    protected function messageText(): string
    {
        return 'Đơn hàng đã hoàn tất. Cảm ơn bạn đã tin tưởng sử dụng dịch vụ.';
    }
}
