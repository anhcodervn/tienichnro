<?php

namespace App\Mail\Orders;

class OrderCreatedMail extends OrderMail
{
    protected function subjectText(): string
    {
        return 'Đã tạo đơn nạp game';
    }

    protected function heading(): string
    {
        return 'Đơn hàng đã được tạo';
    }

    protected function messageText(): string
    {
        return 'Chúng tôi đã nhận thông tin đơn hàng của bạn. Vui lòng hoàn tất thanh toán theo hướng dẫn.';
    }
}
