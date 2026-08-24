@include('errors.partials.page', [
    'statusCode' => 419,
    'eyebrow' => 'Page Expired',
    'headline' => 'Phiên thao tác đã hết hạn',
    'description' => 'Trang đã mở quá lâu hoặc mã bảo vệ biểu mẫu không còn hợp lệ. Dữ liệu chưa được hệ thống xử lý.',
    'helpText' => 'Laravel sử dụng mã CSRF theo phiên để ngăn yêu cầu giả mạo. Khi phiên hết hạn, biểu mẫu cần được tải lại.',
    'hints' => [
        'Tải lại trang để nhận phiên và mã bảo vệ mới.',
        'Kiểm tra lại thông tin trước khi gửi biểu mẫu lần nữa.',
        'Tránh mở cùng một biểu mẫu quá lâu trước khi xác nhận.',
    ],
])
