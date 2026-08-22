<?php

namespace App\Mail\Orders;

class PaymentReceivedMail extends OrderMail
{
    protected function subjectText(): string
    {
        return 'Đã nhận thanh toán';
    }

    protected function heading(): string
    {
        return 'Thanh toán thành công';
    }

    protected function messageText(): string
    {
        return 'Khoản thanh toán đã được ghi nhận và đơn hàng sẽ được chuyển sang xử lý.';
    }
}
