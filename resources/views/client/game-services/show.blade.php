@extends('client.layouts.app')

@section('document_title', 'Dịch vụ game '.$game->name)
@section('description', 'Danh sách dịch vụ game '.$game->name.' đang hoạt động tại NapCarot.')
@section('canonical', route('game-services.show', ['game' => $game]))
@if ($game->image)
    @section('image', $game->image)
    @section('image_alt', 'Dịch vụ game '.$game->name)
@endif

@section('content')
    <section class="client-container grid gap-6 py-6 sm:py-10">
        <header class="overflow-hidden rounded-[12px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-5 shadow-sm sm:p-8">
            <nav class="flex items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                <a class="font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
                <i class="bx bx-chevron-right text-lg text-slate-300" aria-hidden="true"></i>
                <span class="truncate font-bold text-slate-800" aria-current="page">Dịch vụ {{ $game->name }}</span>
            </nav>
            <div class="mt-5 flex items-center gap-4 sm:gap-6">
                @if ($game->image)
                    <img class="h-20 w-20 shrink-0 rounded-[12px] border border-white object-cover shadow-md sm:h-28 sm:w-28" src="{{ $game->image }}" alt="{{ $game->name }}" width="112" height="112">
                @else
                    <span class="grid h-20 w-20 shrink-0 place-items-center rounded-[12px] bg-emerald-600 text-2xl font-extrabold text-white shadow-md sm:h-28 sm:w-28">
                        {{ mb_substr($game->short_name ?: $game->name, 0, 2) }}
                    </span>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">NapCarot · Dịch vụ game</p>
                    <h1 class="mt-2 break-words text-2xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">Dịch vụ game {{ $game->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Chọn dịch vụ phù hợp với nhu cầu của bạn.</p>
                </div>
            </div>
        </header>

        <section aria-labelledby="game-services-title">
            <div>
                <p class="text-sm font-bold text-emerald-700">Dịch vụ đang hoạt động</p>
                <h2 id="game-services-title" class="mt-1 text-2xl font-extrabold text-slate-950">Danh sách dịch vụ</h2>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:mt-5 sm:gap-5 xl:grid-cols-3" data-game-service-list>
                @foreach ($game->gameServices as $service)
                    <a href="{{ route('game-services.service', ['game' => $game, 'gameService' => $service]) }}" class="client-card group overflow-hidden transition hover:border-emerald-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-game-service-card>
                        <div class="relative aspect-[16/9] overflow-hidden bg-gradient-to-br from-slate-100 to-slate-200">
                            @if ($service->background_image)
                                <img class="h-full w-full object-cover transition duration-300 group-hover:scale-105" src="{{ $service->background_image }}" alt="{{ $service->name }}" loading="lazy">
                            @else
                                <span class="grid h-full place-items-center text-3xl text-slate-400 sm:text-5xl"><i class="bx bx-game" aria-hidden="true"></i></span>
                            @endif
                            <span class="absolute right-1.5 top-1.5 rounded-full border border-white/80 bg-white/90 px-2 py-0.5 text-[10px] font-extrabold text-emerald-700 shadow-sm backdrop-blur-sm sm:right-3 sm:top-3 sm:px-2.5 sm:py-1 sm:text-xs" data-game-service-package-count>{{ $service->active_packages_count }} gói</span>
                        </div>
                        <div class="grid gap-2 p-3 sm:gap-3 sm:p-5">
                            <h3 class="line-clamp-2 min-h-10 break-words text-sm font-extrabold leading-5 text-slate-950 sm:min-h-0 sm:text-xl sm:leading-7" data-game-service-card-title>{{ $service->name }}</h3>
                            <p class="text-[11px] font-bold leading-4 text-amber-700 sm:text-sm sm:leading-5" data-game-service-price-range>
                                @if ($service->minimum_price === null)
                                    Giá: Liên hệ
                                @elseif ($service->minimum_price === $service->maximum_price)
                                    Giá từ {{ number_format($service->minimum_price, 0, ',', '.') }}đ
                                @else
                                    Giá từ {{ number_format($service->minimum_price, 0, ',', '.') }}đ - {{ number_format($service->maximum_price, 0, ',', '.') }}đ
                                @endif
                            </p>
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 sm:text-sm"><span class="sm:hidden">Chi tiết</span><span class="hidden sm:inline">Xem dịch vụ</span><i class="bx bx-right-arrow-alt text-base sm:text-lg" aria-hidden="true"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    </section>
@endsection
