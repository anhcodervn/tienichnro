@include('errors.partials.page', [
    'statusCode' => 503,
    'eyebrow' => 'Service Unavailable',
    'headline' => 'Hệ thống đang tạm bảo trì',
    'description' => 'Dịch vụ đang được nâng cấp hoặc tạm ngưng trong thời gian ngắn. Vui lòng quay lại sau.',
    'helpText' => 'Trong thời gian bảo trì, một số chức năng có thể được khóa để bảo đảm dữ liệu luôn nhất quán.',
    'hints' => [
        'Chờ ít phút rồi thử truy cập lại.',
        'Không tạo lại giao dịch nếu bạn đã thanh toán trước đó.',
        'Theo dõi thông báo chính thức để biết thời gian hoạt động trở lại.',
    ],
])
