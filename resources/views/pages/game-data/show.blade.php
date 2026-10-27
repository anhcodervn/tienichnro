@extends('client.layouts.app')
@section('title', $name.' · Dữ liệu game')
@section('robots', 'noindex,follow')
@section('content')
<section class="client-container min-w-0 py-8 sm:py-10">
    <a class="mb-5 inline-flex min-h-11 items-center gap-2 text-sm font-bold text-emerald-700" href="{{ route('nro.game-data.index', ['type' => $dataset]) }}"><i class="bx bx-arrow-back" aria-hidden="true"></i>Danh sách dữ liệu</a>
    <article class="client-card min-w-0 p-4 sm:p-6"><p class="text-xs font-extrabold uppercase tracking-widest text-emerald-700">Dữ liệu đầy đủ</p><h1 class="mt-2 break-words text-2xl font-extrabold text-slate-950">{{ $name }}</h1>@if($effect)<x-game-effect :effect="$effect" :name="$name" :fallback="$media" :fallback-alternative="$mediaFallback" class="mt-5" />@endif<div data-game-data-preview data-preview="{{ json_encode($preview, JSON_THROW_ON_ERROR) }}" data-name="{{ $name }}" class="mt-5 flex flex-wrap items-end gap-4">@if($preview['image'])<img src="{{ $preview['image'] }}" @if($preview['fallback']) data-game-data-fallback="{{ $preview['fallback'] }}" @endif alt="{{ $name }}" data-game-data-image loading="lazy" class="max-h-64 max-w-full object-contain [image-rendering:pixelated]">@endif</div><pre tabindex="0" class="mt-5 max-h-[70dvh] min-w-0 overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-emerald-100 sm:text-sm">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) }}</pre></article>
</section>
@endsection
