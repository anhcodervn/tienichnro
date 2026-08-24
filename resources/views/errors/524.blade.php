@include('errors.partials.page', [
    'statusCode' => 524,
    'eyebrow' => 'Request Timeout',
    'headline' => 'Yêu cầu mất quá nhiều thời gian xử lý',
    'description' => 'Máy chủ hoặc dịch vụ liên quan phản hồi chậm hơn thời gian chờ cho phép. Trạng thái này thường chỉ là tạm thời.',
    'helpText' => 'Lỗi 524 xảy ra khi kết nối phía trước hết thời gian chờ trong lúc máy chủ vẫn đang xử lý yêu cầu.',
    'hints' => [
        'Chờ một lát rồi kiểm tra lại trạng thái.',
        'Không gửi lại liên tục để tránh tạo thao tác trùng.',
        'Liên hệ hỗ trợ nếu yêu cầu vẫn chưa có kết quả.',
    ],
])
