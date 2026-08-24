<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $settings = is_array($systemSettings ?? null) ? $systemSettings : [];
        $siteName = trim((string) ($settings['site_name'] ?? '')) ?: config('app.name', 'Nạp Carot');
        $favicon = trim((string) ($settings['favicon'] ?? ''));
        $logo = trim((string) ($settings['dark_logo'] ?? '')) ?: trim((string) ($settings['light_logo'] ?? ''));
        $primaryAction = is_array($errorActions['primary'] ?? null) ? $errorActions['primary'] : [];
        $secondaryAction = is_array($errorActions['secondary'] ?? null) ? $errorActions['secondary'] : [];
    @endphp
    <title>{{ $statusCode }} - {{ $headline }} | {{ $siteName }}</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#f8fafc">
    @if ($favicon !== '')
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800" rel="stylesheet">
    <x-boxicon />
    @vite('resources/css/client.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center py-8 sm:py-12" data-error-page="{{ $statusCode }}">
        <div class="client-container w-full">
            <section class="mx-auto grid max-w-5xl overflow-hidden rounded-[5px] border border-slate-200 bg-white shadow-xl shadow-slate-200/60 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="min-w-0 p-6 sm:p-10 lg:p-12">
                    <a class="inline-flex max-w-full items-center rounded-[5px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ url('/') }}" aria-label="Về trang chủ {{ $siteName }}">
                        @if ($logo !== '')
                            <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-auto max-h-14 w-auto max-w-48 object-contain object-left">
                        @else
                            <span class="grid h-11 w-11 place-items-center rounded-[5px] bg-emerald-600 font-extrabold text-white">C</span>
                            <span class="ml-3 truncate font-extrabold text-slate-950">{{ $siteName }}</span>
                        @endif
                    </a>

                    <div class="mt-10 flex flex-wrap items-center gap-3">
                        <span class="rounded-[5px] bg-emerald-50 px-3 py-1.5 text-xs font-extrabold uppercase tracking-[0.16em] text-emerald-700">{{ $eyebrow }}</span>
                        <span class="text-sm font-bold text-slate-400">Mã lỗi {{ $statusCode }}</span>
                    </div>
                    <h1 class="mt-4 max-w-3xl break-words text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl">{{ $headline }}</h1>
                    <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">{{ $description }}</p>

                    <div class="mt-7 rounded-[5px] border border-slate-200 bg-slate-50 p-4 sm:p-5">
                        <h2 class="flex items-center gap-2 font-extrabold text-slate-900"><i class="bx bx-info-circle text-xl text-emerald-600" aria-hidden="true"></i>Bạn có thể làm gì?</h2>
                        <ul class="mt-3 grid gap-2.5 text-sm leading-6 text-slate-600">
                            @foreach ($hints as $hint)
                                <li class="flex gap-2.5"><i class="bx bx-check-circle mt-0.5 shrink-0 text-lg text-emerald-600" aria-hidden="true"></i><span>{{ $hint }}</span></li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $primaryAction['href'] ?? url('/') }}" class="client-button gap-2"><i class="bx bx-home-alt-2 text-lg" aria-hidden="true"></i>{{ $primaryAction['label'] ?? 'Về trang chủ' }}</a>
                        <a href="{{ $secondaryAction['href'] ?? route('content.contact') }}" class="client-button-secondary gap-2"><i class="bx bx-support text-lg" aria-hidden="true"></i>{{ $secondaryAction['label'] ?? 'Liên hệ hỗ trợ' }}</a>
                    </div>
                </div>

                <aside class="flex min-w-0 flex-col justify-between border-t border-slate-200 bg-slate-950 p-6 text-white lg:border-l lg:border-t-0 lg:p-8" aria-label="Thông tin lỗi">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">Trạng thái hệ thống</p>
                        <div class="mt-5 flex items-end gap-3">
                            <strong class="text-6xl font-extrabold leading-none tracking-tight text-white sm:text-7xl">{{ $statusCode }}</strong>
                            <span class="pb-1 text-sm font-semibold text-slate-400">HTTP</span>
                        </div>
                        <div class="mt-6 h-1 w-16 rounded-full bg-emerald-400"></div>
                        <p class="mt-6 text-sm leading-7 text-slate-300">{{ $helpText }}</p>
                    </div>
                    <p class="mt-10 border-t border-white/10 pt-5 text-xs leading-5 text-slate-400">Nếu lỗi tiếp tục xuất hiện, hãy gửi mã lỗi và đường dẫn hiện tại cho bộ phận hỗ trợ.</p>
                </aside>
            </section>
        </div>
    </main>
</body>
</html>
