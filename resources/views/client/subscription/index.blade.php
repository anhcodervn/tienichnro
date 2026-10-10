@extends('client.layouts.app')
@section('title', 'Dịch vụ nhận thông báo game')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="client-container grid gap-6 py-6 sm:py-10">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Dịch vụ thông báo</p><h1 class="mt-2 text-2xl font-extrabold text-slate-950">Nhận thông báo game</h1><p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Theo dõi nhân vật qua Zalo hoặc nhận toàn bộ thông báo qua webhook.</p></div>
        @auth<a class="client-button-secondary" href="{{ route('account.wallet') }}">Ví: {{ number_format($wallet->balance, 0, ',', '.') }} đ</a>@endauth
    </header>
    @include('client.subscription.checkout')
    @auth
    <section class="grid gap-4"><h2 class="text-lg font-bold">Gói đã đăng ký</h2>
        @forelse($subscriptions as $subscription)
        <article class="client-card p-4 sm:p-6"><div class="mb-4 flex flex-wrap justify-between gap-3"><div><h3 class="font-bold">{{ $subscription->package_name }}</h3><p class="mt-1 text-xs text-slate-600">{{ $subscription->mode === 'personal' ? 'Cá nhân · Zalo chưa kết nối' : ($subscription->mode === 'webhook' ? 'Webhook' : 'Dịch vụ') }} · {{ $subscription->hasAccess() ? 'Còn hiệu lực' : 'Hết hiệu lực' }} · {{ $subscription->billing_type === 'usage' ? $subscription->remaining_uses.' lượt còn lại' : ($subscription->expires_at ? 'Hết hạn '.$subscription->expires_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') : 'Vĩnh viễn') }}</p></div><span class="text-sm font-bold text-emerald-700">{{ number_format($subscription->price, 0, ',', '.') }} đ</span></div>
            @if($subscription->mode === 'webhook' && $subscription->latestDelivery)
            <p class="mb-3 text-xs text-slate-600">Webhook gần nhất: {{ ['pending' => 'Đang chờ gửi / thử lại', 'processing' => 'Đang gửi', 'delivered' => 'Gửi thành công', 'failed' => 'Gửi thất bại'][''.$subscription->latestDelivery->status] ?? $subscription->latestDelivery->status }} · {{ $subscription->latestDelivery->attempts }} lần thử @if($subscription->latestDelivery->response_status) · HTTP {{ $subscription->latestDelivery->response_status }} @endif</p>
            @endif
            @if($subscription->hasAccess() && $subscription->mode !== 'service')
            <details><summary class="cursor-pointer text-sm font-bold text-emerald-700">Sửa cấu hình nhận tin</summary><form data-notification-subscription data-mode="{{ $subscription->mode }}" data-method="patch" action="{{ route('notification-subscriptions.update', $subscription) }}" class="mt-4 grid gap-4">@csrf @include('client.subscription.fields')<button type="submit" class="client-button-primary justify-self-start">Lưu cấu hình</button></form></details>
            @endif
        </article>
        @empty<p class="text-sm text-slate-500">Bạn chưa đăng ký gói nào.</p>@endforelse
    </section>
    @endauth
</section>
@endsection
