@extends('client.layouts.app')
@section('title', 'Xác minh email')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="client-container py-12"><div class="client-card mx-auto max-w-lg p-7 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-50 text-2xl">✉</span><h1 class="mt-5 text-2xl font-extrabold">Xác minh email của bạn</h1><p class="mt-3 leading-7 text-slate-600">Sau khi xác minh, các đơn guest dùng cùng email sẽ tự động xuất hiện trong lịch sử tài khoản.</p><form class="mt-6" method="POST" action="{{ route('verification.send') }}">@csrf<button class="client-button w-full" type="submit">Gửi lại email xác minh</button></form></div></section>
@endsection
