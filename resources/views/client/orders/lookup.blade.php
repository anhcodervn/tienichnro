@extends('client.layouts.app')
@section('title', 'Tra cứu đơn hàng')
@section('content')
<section class="client-container py-12"><div class="mx-auto max-w-xl"><div class="text-center"><p class="text-sm font-bold text-emerald-700">Bảo mật hai lớp thông tin</p><h1 class="mt-2 text-3xl font-extrabold">Tra cứu đơn hàng</h1><p class="mt-3 text-slate-600">Nhập đồng thời mã đơn và email đã dùng khi mua.</p></div><form class="client-card mt-8 grid gap-5 p-6" method="POST" action="{{ route('orders.lookup.submit') }}">@csrf<label class="client-label">Mã đơn<input class="client-input uppercase" name="code" value="{{ old('code', request()->query('code')) }}" placeholder="TOP260820AB12CD" required></label><label class="client-label">Email<input class="client-input" type="email" name="email" value="{{ old('email') }}" required></label><button class="client-button" type="submit">Tra cứu an toàn</button></form></div></section>
@endsection
