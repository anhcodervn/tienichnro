@props(['title', 'description', 'seo' => null])

<section class="client-container py-6 sm:py-8" data-tool-page>
    <div class="mx-auto grid max-w-4xl gap-6 sm:gap-8">
        <header class="grid gap-3 border-b border-slate-200 pb-5 sm:pb-6" data-tool-header>
            <nav class="flex flex-wrap items-center gap-2 text-xs text-slate-500" aria-label="Đường dẫn công cụ">
                <a class="hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
                <span aria-hidden="true">/</span>
                <a class="hover:text-emerald-700" href="{{ route('home') }}#cong-cu">Công cụ NRO</a>
            </nav>
            <h1 id="tool-page-title" class="text-2xl font-extrabold leading-tight text-slate-900 sm:text-3xl">{{ ($seo['title'] ?? '') ?: $title }}</h1>
            <p class="max-w-3xl text-sm leading-7 text-slate-600">{{ ($seo['excerpt'] ?? '') ?: $description }}</p>
        </header>
        <div class="grid min-w-0 gap-6" data-tool-workspace>{{ $slot }}</div>
        <x-client.tool-seo :seo="$seo" :embedded="true" />
    </div>
</section>
