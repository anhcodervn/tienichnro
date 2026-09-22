@props([
    'title' => 'Thông báo quan trọng',
    'content',
    'published' => false,
])

@php
    $noticeHtml = $content instanceof \Illuminate\Contracts\Support\Htmlable ? $content->toHtml() : '';
@endphp

@if ($published && trim($noticeHtml) !== '')
    <div class="home-notice-banner" role="note" aria-labelledby="home-notice-title">
        <div class="home-notice-header">
            <i class="bx bx-announcement shrink-0 text-xl text-cyan-700" aria-hidden="true"></i>
            <p id="home-notice-title" class="min-w-0"><strong>Thông báo:</strong> {{ $title }}</p>
        </div>
        <div class="article-content article-content--notice home-notice-content">
            {!! $noticeHtml !!}
        </div>
    </div>
@endif
