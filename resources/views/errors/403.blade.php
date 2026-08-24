@include('errors.partials.page', [
    'statusCode' => 403,
    'eyebrow' => 'Forbidden',
    'headline' => 'Bạn không có quyền truy cập trang này',
    'description' => 'Tài khoản hiện tại đã đăng nhập nhưng chưa được cấp quyền sử dụng chức năng hoặc xem nội dung này.',
    'helpText' => 'Quyền truy cập được giới hạn theo vai trò tài khoản nhằm bảo vệ dữ liệu và các thao tác quan trọng.',
    'hints' => [
        'Kiểm tra xem bạn đã đăng nhập đúng tài khoản hay chưa.',
        'Quay lại trang trước và chọn chức năng phù hợp với tài khoản.',
        'Liên hệ hỗ trợ nếu bạn cho rằng đây là sự nhầm lẫn.',
    ],
])
