@include('errors.partials.page', [
    'statusCode' => 520,
    'eyebrow' => 'Unexpected Error',
    'headline' => 'Phản hồi từ hệ thống chưa hợp lệ',
    'description' => 'Yêu cầu đã đến máy chủ nhưng quá trình xử lý nhận được một phản hồi ngoài dự kiến.',
    'helpText' => 'Lỗi 520 thường liên quan đến phản hồi không hợp lệ từ máy chủ hoặc một dịch vụ kết nối phía sau.',
    'hints' => [
        'Tải lại trang sau ít phút.',
        'Kiểm tra lịch sử trước khi gửi lại thao tác quan trọng.',
        'Gửi mã lỗi này cho hỗ trợ nếu tình trạng lặp lại.',
    ],
])
