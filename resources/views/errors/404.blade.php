@include('errors.partials.page', [
    'statusCode' => 404,
    'eyebrow' => 'Not Found',
    'headline' => 'Không tìm thấy trang bạn đang truy cập',
    'description' => 'Liên kết có thể đã thay đổi, nội dung đã được di chuyển hoặc đường dẫn bạn nhập không còn hợp lệ.',
    'helpText' => 'Máy chủ vẫn hoạt động bình thường nhưng không tìm thấy tài nguyên tương ứng với đường dẫn hiện tại.',
    'hints' => [
        'Kiểm tra lại chính tả và các ký tự trong đường dẫn.',
        'Mở lại nội dung từ menu chính hoặc trang bài viết.',
        'Dùng chức năng tìm kiếm nếu bạn đang tìm một bài hướng dẫn.',
    ],
])
