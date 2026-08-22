@extends('client.layouts.app')
@section('title', 'Nạp Carot '.$game->name)
@section('description', $game->seo_description ?: $game->description)
@section('content')
<section class="client-container py-8 sm:py-10 lg:py-12">
    <div class="mb-6 min-w-0">
        <a class="text-sm font-bold text-emerald-700" href="{{ route('home') }}">← Tất cả game</a>
        <h1 class="mt-3 break-words text-2xl font-extrabold tracking-tight sm:text-3xl">Nạp {{ $game->name }}</h1>
        <p class="mt-2 max-w-3xl break-words text-sm leading-6 text-slate-600">{{ $game->description }}</p>
    </div>
    @include('client.components.topup-form', ['games' => collect([$game]), 'selectedGame' => $game, 'walletBalance' => $walletBalance])
</section>
@if ($game->content)<section class="client-container pb-14"><div class="client-card p-6 leading-7 text-slate-700">{{ $game->content }}</div></section>@endif
@endsection
