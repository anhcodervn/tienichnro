@extends('client.layouts.app')
@section('title', 'Thông báo game Ngọc Rồng Online')
@section('description', 'Theo dõi Boss Ngọc Rồng Online theo server: vị trí xuất hiện, người tiêu diệt và thời gian hồi sinh.')
@section('canonical', route('nro.notifies.page'))
@section('robots', request()->query() ? 'noindex,follow' : 'index,follow')
@section('content')
<div class="nro-feed-page bg-slate-50">
<section class="client-container py-8 sm:py-10" @if($service['is_enabled']) data-nro-clock="{{ $snapshot['server_time'] }}" data-nro-realtime="{{ $realtime ? 'true' : 'false' }}" @guest data-nro-guest-expires="{{ $guestExpiresAt * 1000 }}" data-nro-login-url="{{ route('auth.login') }}" @endguest @if($realtime) data-nro-stream="{{ route('nro.notifies.stream', $filters) }}" @endif data-nro-signature="{{ $snapshot['signature'] }}" @endif>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div><p class="text-sm font-bold text-emerald-700">Tiện ích Ngọc Rồng Online</p><h1 class="mt-2 text-3xl font-extrabold text-slate-900">Thông báo game</h1><p class="mt-2 text-slate-600">Theo dõi vòng đời Boss và các thông báo trong game. Giờ hiển thị: Việt Nam (UTC+7).</p></div>
        @if($service['is_enabled'])<a data-nro-refresh class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-slate-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2" href="{{ route('nro.notifies.page', $filters) }}">Làm mới</a>@endif
    </div>
    @if(!$service['is_enabled'])
        <x-client.service-maintenance :message="$service['maintenance_message']" />
    @else
    @guest
        <div class="mb-5 grid gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p>Bạn có thể xem tối đa 30 thông báo mới nhất trong một giờ gần nhất. Cứ 5 phút sẽ có lời nhắc đăng nhập; sau lần nhắc thứ 3, cập nhật trực tiếp sẽ dừng.</p>
            <p>Vui lòng đăng nhập để gỡ bỏ hạn chế.</p>
            <a class="w-fit font-bold underline" href="{{ route('auth.login') }}">Đăng nhập để tiếp tục sử dụng</a>
        </div>
    @endguest
    @include('pages.nro.partials.notification-filters')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-700"><p>Hiển thị <span class="font-bold text-slate-900" data-nro-count>{{ $snapshot['count'] }}</span> / <span class="font-bold text-slate-900" data-nro-total>{{ $snapshot['total'] }}</span> thông báo · <span data-nro-limit>{{ $limit }}</span> thông báo mỗi trang</p><p role="status" aria-live="polite" class="rounded-full bg-emerald-700 px-3 py-1.5 font-semibold text-white" data-nro-status>{{ $realtime ? 'Đang kết nối cập nhật trực tiếp…' : 'Đã dừng cập nhật · vui lòng đăng nhập' }}</p></div>
    <p hidden data-nro-error role="alert" class="mb-4 rounded-[10px] border border-red-200 bg-red-50 p-4 text-sm text-red-700"></p>
    <div hidden data-nro-loading role="status" class="mb-4 flex items-center gap-3 rounded-[10px] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <span aria-hidden="true" class="h-5 w-5 animate-spin rounded-full border-2 border-emerald-200 border-t-emerald-700 motion-reduce:animate-none"></span>Đang tải thông báo…
    </div>
    <div class="grid gap-3 transition-opacity duration-200 motion-reduce:transition-none" data-nro-list role="list" aria-label="Danh sách thông báo game">
        {!! $snapshot['html'] !!}

    </div>
    <div class="mt-5" data-nro-pagination>{!! $snapshot['pagination'] !!}</div>
    <noscript><p class="mt-4 text-sm text-slate-500">Bật JavaScript để nhận thông báo trực tiếp hoặc bấm Làm mới để cập nhật.</p></noscript>
    @endif
</section>
</div>
@endsection
