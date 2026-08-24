@include('errors.partials.page', [
    'statusCode' => 429,
    'eyebrow' => 'Too Many Requests',
    'headline' => 'Bạn thao tác quá nhanh',
    'description' => 'Hệ thống tạm giới hạn yêu cầu để bảo vệ tài khoản và duy trì dịch vụ ổn định.',
    'helpText' => 'Giới hạn tốc độ được áp dụng trong một khoảng thời gian ngắn và sẽ tự động được mở lại.',
    'hints' => [
        'Chờ một lát rồi thử lại thao tác.',
        'Không bấm gửi liên tục hoặc mở quá nhiều yêu cầu đồng thời.',
        'Kiểm tra lịch sử trước khi tạo lại đơn để tránh trùng lặp.',
    ],
])
