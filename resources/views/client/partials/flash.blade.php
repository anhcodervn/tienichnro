@if (session('success') || session('status'))
    <div hidden data-client-alert data-alert-type="success" data-alert-title="Thành công">
        <span data-alert-message>{{ session('success') ?? session('status') }}</span>
    </div>
@endif
@foreach (['error' => ['error', 'Có lỗi xảy ra'], 'auth_google_error' => ['error', 'Không thể đăng nhập Google'], 'warning' => ['warning', 'Lưu ý'], 'info' => ['info', 'Thông báo']] as $key => [$type, $title])
    @if (session($key))
        <div hidden data-client-alert data-alert-type="{{ $type }}" data-alert-title="{{ $title }}">
            <span data-alert-message>{{ session($key) }}</span>
        </div>
    @endif
@endforeach
@if ($errors->any())
    <div hidden data-client-alert data-alert-type="error" data-alert-title="Vui lòng kiểm tra lại thông tin">
        @foreach ($errors->all() as $error)
            <span data-alert-message>{{ $error }}</span>
        @endforeach
    </div>
@endif
