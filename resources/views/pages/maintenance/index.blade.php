<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $settings = $systemSettings ?? [];
        $siteName = $settings['site_name'] ?? config('app.name', 'Nạp Tiền Tự Động');
        $favicon = $settings['favicon'] ?? null;
        $metaTitle = $pageMetaTitle ?? ('Bảo trì hệ thống | '.$siteName);
        $metaDescription = $pageMetaDescription ?? ($settings['site_description'] ?? 'Hệ thống đang bảo trì để nâng cấp dịch vụ.');
        $logo = $settings['light_logo'] ?: ($settings['dark_logo'] ?: null);
        $statusTitle = $settings['system_status_title'] ?: 'Bảo trì hệ thống';
        $statusExcerpt = $settings['system_status_excerpt'] ?: 'Hệ thống đang được đội ngũ kỹ thuật kiểm tra và nâng cấp để đảm bảo hiệu năng, độ ổn định và an toàn dữ liệu.';
        $updatesTitle = $settings['system_updates_title'] ?: 'Cập nhật vận hành';
        $updatesExcerpt = $settings['system_updates_excerpt'] ?: 'Chúng tôi sẽ mở lại truy cập ngay khi hoàn tất các bước kiểm tra cuối cùng.';
        $supportEmail = $settings['support_email'] ?? '';
        $hotline = $settings['hotline'] ?? '';
        $address = $settings['address'] ?? '';
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">

    @if (! empty($favicon))
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:500,700|ibm-plex-sans:400,500,600,700|jetbrains-mono:400,600" rel="stylesheet">
    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Be Vietnam Pro', 'IBM Plex Sans', sans-serif; }
        .font-tech { font-family: 'Space Grotesk', 'Be Vietnam Pro', sans-serif; }
        .font-mono-tech { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <div class="relative isolate overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.24),transparent_30%),radial-gradient(circle_at_bottom_right,rgba(59,130,246,0.22),transparent_28%),linear-gradient(145deg,#020617_0%,#0f172a_52%,#111827_100%)]"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,0.08)_1px,transparent_1px)] bg-[size:56px_56px] opacity-40"></div>

        <main class="relative mx-auto flex min-h-screen w-full max-w-7xl items-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="grid w-full gap-8 lg:grid-cols-[minmax(0,1.15fr)_420px]">
                <section class="rounded-[28px] border border-white/10 bg-white/5 p-6 shadow-[0_30px_120px_rgba(2,6,23,0.45)] backdrop-blur-xl sm:p-8 lg:p-10">
                    <div class="flex flex-wrap items-center gap-4">
                        @if (! empty($logo))
                            <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-12 w-auto rounded-xl bg-white/95 p-2">
                        @else
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-sm font-bold text-slate-950">
                                {{ \Illuminate\Support\Str::substr($siteName, 0, 2) }}
                            </div>
                        @endif

                        <div>
                            <p class="font-mono-tech text-xs uppercase tracking-[0.32em] text-sky-200">System Maintenance</p>
                            <h1 class="font-tech mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">
                                {{ $statusTitle }}
                            </h1>
                        </div>
                    </div>

                    <p class="mt-6 max-w-3xl text-base leading-8 text-slate-200 sm:text-lg">
                        {{ $statusExcerpt }}
                    </p>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/10 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-cyan-200">Trạng thái</p>
                            <p class="mt-3 text-lg font-semibold text-white">Đang bảo trì có kiểm soát</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Truy cập tạm thời giới hạn để đảm bảo an toàn và tính toàn vẹn của hệ thống.</p>
                        </div>
                        <div class="rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-emerald-200">Đội ngũ xử lý</p>
                            <p class="mt-3 text-lg font-semibold text-white">Theo dõi liên tục</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Kỹ thuật và vận hành đang kiểm tra từng bước trước khi mở lại toàn bộ dịch vụ.</p>
                        </div>
                        <div class="rounded-2xl border border-amber-400/20 bg-amber-400/10 p-4">
                            <p class="text-xs uppercase tracking-[0.22em] text-amber-200">Khuyến nghị</p>
                            <p class="mt-3 text-lg font-semibold text-white">Vui lòng thử lại sau</p>
                            <p class="mt-2 text-sm leading-6 text-slate-300">Nếu cần hỗ trợ gấp, hãy liên hệ trực tiếp qua hotline hoặc email bên dưới.</p>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button type="button" onclick="window.location.reload()" class="inline-flex items-center justify-center rounded-full bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                            Tải lại trang
                        </button>
                        @if (! empty($supportEmail))
                            <a href="mailto:{{ $supportEmail }}" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-5 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                                Email hỗ trợ
                            </a>
                        @endif
                        <a href="{{ url('/trang-thai-he-thong') }}" class="inline-flex items-center justify-center rounded-full border border-sky-300/25 bg-sky-300/10 px-5 py-3 text-sm font-semibold text-sky-100 transition hover:bg-sky-300/15">
                            Xem trạng thái hệ thống
                        </a>
                    </div>

                    @if (filled($maintenanceStatusHtml->toHtml()))
                        <section class="mt-10 rounded-[24px] border border-white/10 bg-slate-950/40 p-6">
                            <div class="flex items-center gap-3">
                                <div class="h-2.5 w-2.5 rounded-full bg-cyan-300"></div>
                                <h2 class="font-tech text-xl font-semibold text-white">Chi tiết thông báo</h2>
                            </div>
                            <div class="prose prose-invert mt-5 max-w-none prose-headings:font-['Space_Grotesk',sans-serif] prose-headings:text-white prose-p:text-slate-300 prose-li:text-slate-300">
                                {!! $maintenanceStatusHtml !!}
                            </div>
                        </section>
                    @endif
                </section>

                <aside class="space-y-5">
                    <section class="rounded-[28px] border border-white/10 bg-white/6 p-6 backdrop-blur-xl">
                        <p class="font-mono-tech text-xs uppercase tracking-[0.28em] text-slate-300">Hỗ trợ</p>
                        <h2 class="font-tech mt-3 text-2xl font-bold text-white">Thông tin liên hệ</h2>
                        <div class="mt-5 grid gap-3 text-sm text-slate-200">
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Hotline</p>
                                <p class="mt-2 text-base font-semibold text-white">{{ $hotline ?: 'Đang cập nhật' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Email</p>
                                <p class="mt-2 break-all text-base font-semibold text-white">{{ $supportEmail ?: 'Đang cập nhật' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Địa chỉ</p>
                                <p class="mt-2 text-base font-semibold text-white">{{ $address ?: 'Đang cập nhật' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-[28px] border border-white/10 bg-white/6 p-6 backdrop-blur-xl">
                        <p class="font-mono-tech text-xs uppercase tracking-[0.28em] text-slate-300">Vận hành</p>
                        <h2 class="font-tech mt-3 text-2xl font-bold text-white">{{ $updatesTitle }}</h2>
                        <p class="mt-3 text-sm leading-7 text-slate-300">{{ $updatesExcerpt }}</p>

                        @if (filled($maintenanceUpdatesHtml->toHtml()))
                            <div class="prose prose-invert mt-5 max-w-none prose-headings:font-['Space_Grotesk',sans-serif] prose-headings:text-white prose-p:text-slate-300 prose-li:text-slate-300">
                                {!! $maintenanceUpdatesHtml !!}
                            </div>
                        @endif

                        <div class="mt-5 flex flex-wrap gap-3">
                            <a href="{{ url('/cap-nhat-he-thong') }}" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-white/5 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">
                                Nhật ký cập nhật
                            </a>
                            <a href="{{ url('/') }}" class="inline-flex items-center justify-center rounded-full border border-white/15 bg-transparent px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-white/10">
                                Thử truy cập lại
                            </a>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </div>
</body>
</html>
