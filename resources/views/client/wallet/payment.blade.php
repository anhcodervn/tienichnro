@extends('client.layouts.app')

@section('title', 'Thanh toán chuyển khoản')
@section('robots', 'noindex,nofollow')

@section('content')
<x-client.bank-transfer-payment
    eyebrow="Yêu cầu nạp {{ $deposit['code'] }}"
    title="Thanh toán chuyển khoản"
    subtitle="Hoàn tất thanh toán trước khi hết thời gian để số dư được cộng tự động."
    :code="$deposit['code']"
    :status="$deposit['status']"
    :bank-name="$deposit['bank_name']"
    :account-name="$deposit['account_name']"
    :account-number="$deposit['account_number']"
    :amount="$deposit['amount']"
    :content="$deposit['content']"
    :qr-url="$deposit['qr_url']"
    :back-url="route('wallet.deposit.index')"
    back-label="Quay lại nạp tiền"
    :show-countdown="true"
    data-wallet-payment
    data-transaction-id="{{ $deposit['id'] }}"
    data-confirm-url="{{ url('/api/client/wallet/deposit-requests/'.$deposit['id'].'/confirm') }}"
    data-wallet-channel="users.{{ $user->id }}.wallet"
    data-expires-at="{{ $deposit['expires_at'] }}"
    data-current-status="{{ $deposit['status'] }}"
>
    <x-slot:actions>
        <button class="client-button w-full gap-2 bg-indigo-600 hover:bg-indigo-700" type="button" data-payment-confirm @if (! $deposit['can_confirm']) hidden @endif>
            <i class="bx bx-refresh text-lg" aria-hidden="true"></i><span data-payment-confirm-text>Tôi đã chuyển khoản</span>
        </button>
        <a class="client-button-secondary mt-3 w-full bg-white" href="{{ route('wallet.deposit.index', ['tab' => 'history']) }}">Xem lịch sử nạp tiền</a>
        <p class="mt-4 text-center text-xs text-slate-500" data-payment-realtime-text>Trang sẽ tự cập nhật khi thanh toán thành công.</p>
    </x-slot:actions>
</x-client.bank-transfer-payment>
@endsection
