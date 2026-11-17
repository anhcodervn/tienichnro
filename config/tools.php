<?php

return [
    'items' => [
        ['code' => 'game_image', 'name' => 'Ghép ảnh game', 'description' => 'Ghép panel tài khoản NRO và chọn item nổi bật, kèm chi tiết tùy chọn.', 'icon' => 'bx-images', 'route' => 'tools.game-image', 'parameters' => []],
        ['code' => 'game_notifications', 'name' => 'Thông báo game', 'description' => 'Thông báo NRO theo server và lịch sử vòng đời Boss.', 'icon' => 'bx-bell', 'route' => 'nro.notifies.page', 'parameters' => []],
        ['code' => 'game_password', 'name' => 'Đổi mật khẩu game', 'description' => 'Đổi mật khẩu Ngọc Rồng Online đơn lẻ hoặc hàng loạt, xử lý nền và theo dõi lịch sử.', 'icon' => 'bx-lock-alt', 'route' => 'tools.game-password', 'parameters' => []],
        ['code' => 'account_verification', 'name' => 'Xác minh tài khoản', 'description' => 'Lấy cú pháp xác minh tài khoản NRO, chọn danh sách và mở SMS gửi 6020 trên điện thoại.', 'icon' => 'bx-shield-quarter', 'route' => 'tools.game-verification', 'parameters' => []],
        ['code' => 'game_mods', 'name' => 'Tải mod', 'description' => 'Công cụ tải mod game đang được phát triển.', 'icon' => 'bx-download', 'route' => null, 'parameters' => []],
    ],
];
