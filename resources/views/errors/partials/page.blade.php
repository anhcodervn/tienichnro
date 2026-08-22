<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $settings = $systemSettings ?? [];
        $siteName = $settings['site_name'] ?? config('app.name', 'Nạp Carot');
        $favicon = $settings['favicon'] ?? null;
        $logo = $settings['light_logo'] ?: ($settings['dark_logo'] ?: null);
    @endphp
    <title>{{ $statusCode }} - {{ $headline }} | {{ $siteName }}</title>
    @if (!empty($favicon))
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700|space-grotesk:500,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>

<body class="min-h-screen bg-slate-950 font-['Be_Vietnam_Pro',sans-serif] text-white">
    <main class="mx-auto flex min-h-screen max-w-5xl items-center px-4 py-10">
        <div class="w-full rounded-[24px] border border-white/10 bg-white/5 p-8 shadow-xl backdrop-blur-xl">
            @if (! empty($logo))
                <img src="{{ $logo }}" alt="{{ $siteName }}" class="mb-6 h-11 max-w-48 object-contain object-left">
            @endif
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-200">{{ $eyebrow }}</p>
            <h1 class="mt-4 text-4xl font-black tracking-tight">{{ $headline }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 text-slate-300">{{ $description }}</p>

            <div class="mt-8 grid gap-4 md:grid-cols-2">
                <div class="rounded-[16px] border border-white/10 bg-white/5 p-5">
                    <p class="text-sm font-semibold text-white">Tình huống</p>
                    <p class="mt-2 text-sm leading-7 text-slate-300">{{ $helpText }}</p>
                </div>
                <div class="rounded-[16px] border border-white/10 bg-white/5 p-5">
                    <p class="text-sm font-semibold text-white">Gợi ý xử lý</p>
                    <ul class="mt-2 space-y-2 text-sm leading-7 text-slate-300">
                        @foreach ($hints as $hint)
                            <li>{{ $hint }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="{{ $errorActions['primary']['href'] ?? '/' }}" class="inline-flex items-center justify-center rounded-full bg-emerald-400 px-5 py-3 text-sm font-semibold text-slate-950">
                    {{ $errorActions['primary']['label'] ?? 'Về trang chủ' }}
                </a>
                <a href="{{ $errorActions['secondary']['href'] ?? '/lien-he' }}" class="inline-flex items-center justify-center rounded-full border border-white/12 bg-white/5 px-5 py-3 text-sm font-semibold text-white">
                    {{ $errorActions['secondary']['label'] ?? 'Liên hệ hỗ trợ' }}
                </a>
            </div>
        </div>
    </main>
</body>

</html>
