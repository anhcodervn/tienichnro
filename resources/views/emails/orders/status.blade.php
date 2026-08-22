<x-mail::message>
# {{ $heading }}

{{ $messageText }}

<x-mail::panel>
Mã đơn: **{{ $order->code }}**

Game: **{{ $order->game?->name ?? 'Không xác định' }}**

Máy chủ: **{{ $order->server?->name ?? 'Không áp dụng' }}**

Tài khoản: **{{ $order->purchase_mode === 'bulk' ? $recipientCount.' tài khoản' : $order->game_account }}**

Gói nạp: **{{ $order->package_name }} × {{ $order->quantity }}**

Tổng tiền: **{{ number_format((int) $order->total_amount, 0, ',', '.') }}đ**
</x-mail::panel>

<x-mail::button :url="$orderUrl">
Kiểm tra đơn hàng
</x-mail::button>

Trân trọng,

{{ config('app.name') }}
</x-mail::message>
