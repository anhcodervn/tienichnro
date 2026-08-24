@include('errors.partials.page', [
    'statusCode' => 401,
    'eyebrow' => 'Unauthorized',
    'headline' => 'Bạn cần đăng nhập để tiếp tục',
    'description' => 'Phiên hiện tại chưa được xác thực hoặc đã hết hạn. Hãy đăng nhập lại để truy cập nội dung này.',
    'helpText' => 'Hệ thống yêu cầu một phiên đăng nhập hợp lệ trước khi cho phép truy cập tài nguyên được bảo vệ.',
    'hints' => [
        'Đăng nhập bằng đúng tài khoản đã sử dụng trước đó.',
        'Nếu vừa đổi mật khẩu, hãy đăng nhập lại để tạo phiên mới.',
        'Không chia sẻ mật khẩu hoặc mã xác thực với người khác.',
    ],
])
