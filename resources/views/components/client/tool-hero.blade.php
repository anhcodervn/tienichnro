@props(['title', 'description', 'seo' => null])

<header {{ $attributes->merge(['class' => 'grid min-w-0 gap-4 rounded-[10px] border border-emerald-200 bg-gradient-to-r from-emerald-50 via-white to-cyan-50 p-4 shadow-sm sm:p-6']) }} data-tool-header data-tool-hero>
    <nav class="flex min-w-0 flex-wrap items-center gap-2 text-xs text-slate-500" aria-label="Đường dẫn công cụ">
        <a class="font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
        <i class="bx bx-chevron-right text-base text-slate-400" aria-hidden="true"></i>
        <span class="min-w-0 break-words font-medium text-emerald-800" aria-current="page">{{ $seo['tool_name'] ?? $title }}</span>
    </nav>
    <div class="flex min-w-0 flex-wrap items-center justify-between gap-4">
        <div class="min-w-0 flex-1 basis-64">
            <h1 id="tool-page-title" class="break-words text-[22px] font-extrabold leading-tight tracking-tight text-slate-900 sm:text-[26px]">{{ ($seo['title'] ?? '') ?: $title }}</h1>
            @if(($seo['excerpt'] ?? '') ?: $description)
                <p class="mt-2 text-sm leading-7 text-slate-600">{{ ($seo['excerpt'] ?? '') ?: $description }}</p>
            @endif
        </div>
        @if($slot->isNotEmpty())<div class="shrink-0">{{ $slot }}</div>@endif
    </div>
</header>
