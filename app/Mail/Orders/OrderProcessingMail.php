<?php

namespace App\Mail\Orders;

class OrderProcessingMail extends OrderMail
{
    protected function subjectText(): string
    {
        return 'Đơn nạp game đang xử lý';
    }

    protected function heading(): string
    {
        return 'Đơn hàng đang được xử lý';
    }

    protected function messageText(): string
    {
        return 'Đội ngũ vận hành đang thực hiện nạp game. Bạn sẽ nhận email ngay khi đơn hoàn tất.';
    }
}
