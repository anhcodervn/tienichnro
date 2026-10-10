@extends('client.layouts.app')
@section('title', 'Ví & lịch sử dòng tiền')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="client-container grid gap-6 py-8 sm:py-10">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-extrabold text-slate-950">Ví & lịch sử dòng tiền</h1><p class="mt-2 text-sm text-slate-600">Theo dõi số dư và từng giao dịch của bạn.</p></div>
        <div class="flex flex-wrap gap-2"><a class="client-button-primary" href="{{ route('notification-subscriptions.index') }}">Nhận thông báo game</a><a class="client-button-secondary" href="{{ route('account.index') }}">Tài khoản</a></div>
    </div>
    <div class="client-card p-5 sm:p-6"><p class="text-sm font-semibold text-slate-600">Số dư hiện tại</p><p class="mt-2 text-3xl font-extrabold text-emerald-700">{{ number_format($wallet->balance, 0, ',', '.') }} đ</p><p class="mt-3 text-sm text-slate-600">Liên hệ quản trị viên để nạp tiền vào ví.</p><a class="mt-2 inline-block text-sm font-bold text-emerald-700" href="{{ route('content.contact') }}">Liên hệ hỗ trợ</a></div>
    <div class="client-card overflow-hidden">
        <h2 class="border-b border-slate-200 px-4 py-4 font-bold">Lịch sử dòng tiền</h2>
        <div class="overflow-x-auto"><table class="w-full whitespace-nowrap text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-600"><tr><th class="p-4">Thời gian</th><th class="p-4">Nội dung</th><th class="p-4">Số tiền</th><th class="p-4">Số dư trước</th><th class="p-4">Số dư sau</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            <tr class="border-t border-slate-100"><td class="p-4 text-xs">{{ $transaction->created_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i:s') }}</td><td class="max-w-sm whitespace-normal p-4">{{ $transaction->description }}</td><td class="p-4 font-bold {{ $transaction->direction === 'credit' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $transaction->direction === 'credit' ? '+' : '-' }}{{ number_format($transaction->amount, 0, ',', '.') }} đ</td><td class="p-4">{{ number_format($transaction->balance_before, 0, ',', '.') }} đ</td><td class="p-4">{{ number_format($transaction->balance_after, 0, ',', '.') }} đ</td></tr>
        @empty
            <tr><td colspan="5" class="p-6 text-center text-slate-500">Chưa có giao dịch.</td></tr>
        @endforelse
        </tbody></table></div>
    </div>
    {{ $transactions->links() }}
</section>
@endsection
