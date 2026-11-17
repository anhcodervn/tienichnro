<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Tiện ích NRO - Ghép ảnh game</title>
    @vite(['resources/css/game-image-editor.css', 'resources/js/game-image-editor.ts'])
</head>
<body>
    <div data-game-image-editor data-account="{{ json_encode($account, JSON_THROW_ON_ERROR) }}"
         data-demo-base="{{ asset('images/demo/capture/kztiger') }}" data-index-url="{{ route('tools.game-image') }}"
         data-home-url="{{ route('home') }}" data-logo-url="{{ $studioLogo }}" data-read-only="{{ ($readOnly ?? false) ? 'true' : 'false' }}">
        <p role="status">Đang mở công cụ ghép ảnh Tiện ích NRO…</p>
        <noscript>Cần bật JavaScript để sử dụng trình chỉnh ảnh.</noscript>
    </div>
</body>
</html>
