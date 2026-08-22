@extends('client.layouts.app')
@section('title', 'Đặt lại mật khẩu')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="client-container py-12"><form class="client-card mx-auto grid max-w-md gap-5 p-6" method="POST" action="{{ route('password.store') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><h1 class="text-2xl font-extrabold">Đặt lại mật khẩu</h1><label class="client-label">Email<input class="client-input" type="email" name="email" value="{{ old('email', $email) }}" required></label><label class="client-label">Mật khẩu mới<input class="client-input" type="password" name="password" required></label><label class="client-label">Xác nhận mật khẩu<input class="client-input" type="password" name="password_confirmation" required></label><button class="client-button" type="submit">Cập nhật mật khẩu</button></form></section>
@endsection
