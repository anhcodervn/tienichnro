@include('errors.partials.page', [
    'statusCode' => 500,
    'eyebrow' => 'Server Error',
    'headline' => 'Hệ thống chưa thể hoàn tất yêu cầu',
    'description' => 'Một lỗi nội bộ đã xảy ra trong lúc xử lý. Thông tin kỹ thuật đã được ghi nhận để kiểm tra.',
    'helpText' => 'Đây thường là lỗi tạm thời ở ứng dụng hoặc dịch vụ liên quan, không nhất thiết xuất phát từ thao tác của bạn.',
    'hints' => [
        'Chờ vài phút rồi tải lại trang.',
        'Kiểm tra lịch sử trước khi lặp lại thao tác thanh toán hoặc tạo đơn.',
        'Liên hệ hỗ trợ nếu lỗi tiếp tục xuất hiện.',
    ],
])
