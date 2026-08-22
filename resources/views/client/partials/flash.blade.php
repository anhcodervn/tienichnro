@if (session('success') || session('status'))
    <div hidden data-client-alert data-alert-type="success" data-alert-title="Thành công">
        <span data-alert-message>{{ session('success') ?? session('status') }}</span>
    </div>
@endif
@if ($errors->any())
    <div hidden data-client-alert data-alert-type="error" data-alert-title="Vui lòng kiểm tra lại thông tin">
        @foreach ($errors->all() as $error)
            <span data-alert-message>{{ $error }}</span>
        @endforeach
    </div>
@endif
