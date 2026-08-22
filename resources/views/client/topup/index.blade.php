@extends('client.layouts.app')
@section('title', 'Nạp game Teamobi')
@section('content')
<section class="client-container py-8 sm:py-14"><div class="max-w-2xl"><p class="text-sm font-bold text-emerald-700">Chọn trò chơi</p><h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Bạn muốn nạp game nào?</h1><p class="mt-4 leading-7 text-slate-600">Danh sách được quản lý trực tiếp từ hệ thống. Game tạm ngừng sẽ tự động ẩn.</p></div>
<div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach ($games as $game)<a class="client-card p-6 transition hover:border-emerald-300 hover:shadow-md" href="{{ route('topup.game', $game) }}"><span class="grid h-12 w-12 place-items-center rounded-[5px] bg-emerald-50 font-extrabold text-emerald-700">{{ mb_substr($game->short_name ?: $game->name, 0, 2) }}</span><h2 class="mt-5 text-xl font-bold">{{ $game->name }}</h2><p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-500">{{ $game->description }}</p><span class="mt-5 inline-block text-sm font-bold text-emerald-700">Chọn game →</span></a>@endforeach</div></section>
@endsection
