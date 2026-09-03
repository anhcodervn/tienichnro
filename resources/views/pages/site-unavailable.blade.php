<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }} - {{ $domain }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#f8fafc">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800" rel="stylesheet">
    @vite('resources/css/client.css')
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="relative isolate grid min-h-screen place-items-center overflow-hidden px-4 py-8 sm:px-6 sm:py-12" data-site-unavailable="{{ $state }}">
        <div class="absolute inset-0 -z-20 bg-slate-50"></div>
        <div class="absolute inset-x-0 top-0 -z-10 h-72 bg-gradient-to-b from-amber-50 to-transparent"></div>

        <section class="w-full max-w-3xl overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-xl shadow-slate-200/70">
            <header class="flex flex-col gap-3 border-b border-amber-200 bg-amber-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-amber-300 bg-white text-2xl text-amber-600 shadow-sm">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-status-icon="warning">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 8v4"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </span>
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-amber-700">Cảnh báo truy cập</p>
                        <p class="mt-0.5 text-sm font-semibold text-amber-950">
                            {{ $state === 'suspended' ? 'Website đang tạm ngưng hoạt động' : 'Tên miền đã được trỏ thành công' }}
                        </p>
                    </div>
                </div>
                <span class="w-fit rounded-full border border-amber-300 bg-white px-3 py-1.5 text-xs font-bold text-amber-800">HTTP {{ $statusCode }}</span>
            </header>

            <div class="grid gap-7 p-6 sm:p-8 md:grid-cols-[5rem_minmax(0,1fr)] md:p-10">
                <div class="grid h-20 w-20 place-items-center rounded-2xl border-2 border-amber-200 bg-amber-50 text-4xl text-amber-600">
                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-status-icon="clock">
                        <circle cx="12" cy="12" r="9"></circle>
                        <path d="M12 7v5l3 2"></path>
                    </svg>
                </div>

                <div class="grid min-w-0 gap-6">
                    <div class="grid gap-3">
                        @if ($siteName)
                            <p class="truncate text-sm font-bold text-indigo-600">{{ $siteName }}</p>
                        @endif
                        <h1 class="text-2xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-3xl">{{ $title }}</h1>
                    </div>

                    <div class="grid gap-2">
                        <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-slate-600">Tên miền đang truy cập</p>
                        <div class="flex min-h-12 items-center gap-3 rounded-xl border-2 border-slate-300 bg-slate-50 px-4 shadow-inner shadow-slate-100">
                            <svg class="h-5 w-5 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-status-icon="domain">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path>
                            </svg>
                            <span class="min-w-0 break-all font-mono text-sm font-bold text-slate-900">{{ $domain }}</span>
                        </div>
                    </div>

                    <div class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-status-icon="information">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 11v5M12 8h.01"></path>
                        </svg>
                        <p class="text-sm font-medium leading-6">{{ $description }}</p>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs leading-5 text-slate-500">Trang sẽ hoạt động bình thường ngay sau khi được kích hoạt.</p>
                        <a href="{{ request()->fullUrl() }}" class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" data-status-icon="refresh">
                                <path d="M20 6v5h-5"></path>
                                <path d="M19 11a7 7 0 1 0 1 5"></path>
                            </svg>
                            Kiểm tra lại
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
