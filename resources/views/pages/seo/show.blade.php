@extends('client.layouts.app')
@section('title', $pageMetaTitle)
@section('description', $pageMetaDescription)
@section('canonical', $pageMetaCanonical)
@section('image', $pageMetaImage)
@section('og_type', 'article')
@section('content')
<article class="client-container py-10"><div class="mx-auto min-w-0 max-w-3xl"><a class="text-sm font-bold text-emerald-700" href="{{ route('seo.index') }}">← Tin tức</a><p class="mt-7 break-words text-xs font-bold uppercase tracking-wider text-emerald-700">{{ $post->category?->name ?: 'Hướng dẫn' }}</p><h1 class="mt-3 break-words text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ $post->title }}</h1><p class="mt-4 text-sm text-slate-500">{{ $post->published_at?->format('d/m/Y') }} · {{ $readingMinutes }} phút đọc</p>@if ($coverImage)<img class="mt-8 aspect-video w-full max-w-full rounded-2xl object-cover" src="{{ $coverImage }}" alt="{{ $post->title }}">@endif<div class="client-card mt-8 min-w-0 max-w-full break-words p-6 leading-8 text-slate-700 sm:p-8">{!! $contentHtml !!}</div></div></article>
@endsection
