@extends('client.layouts.app')
@section('title', 'Quên mật khẩu')
@section('robots', 'noindex,follow')
@section('content')
<section class="client-container py-12"><form class="client-card mx-auto grid max-w-md gap-5 p-6" method="POST" action="{{ route('password.email') }}">@csrf<div><h1 class="text-2xl font-extrabold">Quên mật khẩu</h1><p class="mt-2 text-sm leading-6 text-slate-600">Nhập email để nhận liên kết đặt lại mật khẩu.</p></div><label class="client-label">Email<input class="client-input" type="email" name="email" value="{{ old('email') }}" required autofocus></label><button class="client-button" type="submit">Gửi liên kết</button></form></section>
@endsection
