<!DOCTYPE html>
<html lang="vi" data-client-theme>
<head>
    <script data-client-theme-init>
        (() => {
            let theme;
            try { theme = localStorage.getItem('client-theme'); } catch {}
            const dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $settings = $systemSettings ?? [];
        $siteName = trim((string) ($settings['site_name'] ?? '')) ?: config('app.name', 'Tiện ích NRO');
        $title = trim($__env->yieldContent('title')) ?: ($settings['meta_title'] ?? $siteName);
        $description = trim($__env->yieldContent('description')) ?: ($settings['meta_description'] ?? $settings['site_description'] ?? 'Tiện ích và hướng dẫn game Ngọc Rồng Online.');
        $keywords = trim($__env->yieldContent('keywords'));
        $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
        $favicon = trim((string) ($settings['favicon'] ?? ''));
        $headerLogo = trim((string) ($settings['dark_logo'] ?? '')) ?: trim((string) ($settings['light_logo'] ?? ''));
        $shareImage = trim($__env->yieldContent('image')) ?: trim((string) ($settings['og_image'] ?? ''));
        $shareImage = $shareImage !== '' && ! \Illuminate\Support\Str::startsWith($shareImage, ['http://', 'https://'])
            ? url($shareImage)
            : $shareImage;
        $shareImageAlt = trim($__env->yieldContent('image_alt'));
        $explicitDocumentTitle = trim($__env->yieldContent('document_title'));
        $documentTitle = $explicitDocumentTitle !== ''
            ? $explicitDocumentTitle
            : ($title === $siteName || \Illuminate\Support\Str::endsWith($title, ' | '.$siteName)
                ? $title
                : $title.' | '.$siteName);
        $loadsClientTracking = ! request()->routeIs(['auth.*', 'password.*', 'verification.*']);
        $gtmId = $loadsClientTracking ? ($clientTracking['gtm_id'] ?? '') : '';
        $metaPixelId = $loadsClientTracking ? ($clientTracking['meta_pixel_id'] ?? '') : '';
        $customHeadTags = $loadsClientTracking ? ($inlineSeoCode['head'] ?? '') : '';
        $customScript = $loadsClientTracking ? ($inlineSeoCode['script'] ?? '') : '';
    @endphp
    <title>{{ $documentTitle }}</title>
    <meta name="description" content="{{ $description }}">
    @if ($keywords !== '')
        <meta name="keywords" content="{{ $keywords }}">
    @endif
    <meta name="robots" content="@yield('robots', $settings['robots'] ?? 'index,follow')">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $documentTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta name="twitter:card" content="{{ $shareImage !== '' ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $documentTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if ($shareImage !== '')
        <meta property="og:image" content="{{ $shareImage }}">
        <meta name="twitter:image" content="{{ $shareImage }}">
        @if ($shareImageAlt !== '')
            <meta property="og:image:alt" content="{{ $shareImageAlt }}">
            <meta name="twitter:image:alt" content="{{ $shareImageAlt }}">
        @endif
    @endif
    @if ($favicon !== '')
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif
    @if ($gtmId !== '')
        <script data-site-gtm>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                var f = d.getElementsByTagName(s)[0], j = d.createElement(s), dl = l !== 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', {{ Illuminate\Support\Js::from($gtmId) }});
        </script>
    @endif
    @if ($metaPixelId !== '')
        <script data-site-meta-pixel>
            !function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = true;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = true;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s);
            }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', {{ Illuminate\Support\Js::from($metaPixelId) }});
            fbq('track', 'PageView');
        </script>
    @endif
    @if ($customHeadTags !== '')
        <!-- custom-head-tags -->
        {!! $customHeadTags !!}
        <!-- /custom-head-tags -->
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800" rel="stylesheet">
    <x-boxicon />
    @vite('resources/css/client.css')
    @if (($customCodeAssets['css'] ?? false) && ! request()->routeIs(['auth.*', 'password.*', 'verification.*']))
        <link rel="stylesheet" href="{{ route('site_custom.css') }}" data-site-custom-css>
    @endif
    @stack('head')
</head>
<body class="pb-[calc(4rem+env(safe-area-inset-bottom))] bg-gray-50 lg:pb-0" data-authenticated="{{ auth()->check() ? 'true' : 'false' }}">
    @if ($gtmId !== '')
        <noscript data-site-gtm-noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @if ($metaPixelId !== '')
        <noscript data-site-meta-pixel-noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ urlencode($metaPixelId) }}&amp;ev=PageView&amp;noscript=1" alt=""></noscript>
    @endif
    <a href="#main-content" class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-[5px] focus:bg-slate-950 focus:px-4 focus:py-3 focus:text-white">Bỏ qua điều hướng</a>
    @php
        $clientLinks = [
            ['label' => 'Trang chủ', 'url' => route('home'), 'icon' => 'bx-home-alt-2', 'active' => request()->routeIs('home')],
            ['label' => 'Công cụ', 'url' => route('home').'#cong-cu', 'icon' => 'bx-joystick', 'active' => false, 'opens_tools' => true],
            ['label' => 'Tin tức', 'url' => route('seo.index'), 'icon' => 'bx-news', 'active' => request()->routeIs('seo.*')],
        ];
    @endphp
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur lg:z-40">
        <div class="client-container flex min-h-[4.5rem] items-center justify-between gap-3 sm:min-h-20">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-[5px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-client-home>
                @if ($headerLogo !== '')
                    <img src="{{ $headerLogo }}" alt="{{ $siteName }}" class="h-10 w-auto max-w-[5rem] shrink-0 object-contain object-left sm:h-12 sm:max-w-[12rem] lg:h-14 lg:max-w-none">
                @else
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] bg-emerald-600 font-extrabold text-white">N</span><span class="truncate font-extrabold tracking-tight text-slate-950">{{ $siteName }}</span>
                @endif
            </a>
            <nav class="hidden min-w-0 items-center gap-2 text-sm font-semibold text-slate-700 lg:flex xl:gap-4" aria-label="Điều hướng chính">
                @foreach ($clientLinks as $link)
                    <a @class(['inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-[5px] px-2 transition hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600', 'text-emerald-700' => $link['active']]) href="{{ $link['url'] }}" @if($link['opens_tools'] ?? false) data-client-tools-open role="button" aria-haspopup="dialog" aria-controls="client-tools-modal" @endif @if($link['active']) aria-current="page" @endif><i class="bx {{ $link['icon'] }} text-lg" aria-hidden="true"></i>{{ $link['label'] }}</a>
                @endforeach
            </nav>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" data-client-theme-toggle aria-label="Đổi giao diện sáng/tối" aria-pressed="false" title="Đổi giao diện sáng/tối" class="grid h-11 w-11 shrink-0 place-items-center rounded-[10px] border border-slate-200 bg-white text-xl text-slate-700 transition hover:border-emerald-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600">
                    <i class="bx bx-moon dark:hidden" aria-hidden="true"></i><i class="bx bx-sun hidden dark:block" aria-hidden="true"></i>
                </button>
                @auth
                    <details class="group relative" data-client-menu>
                        <summary class="flex h-12 cursor-pointer list-none items-center gap-2 rounded-[5px] border border-slate-200 bg-white px-2.5 text-left shadow-sm transition hover:border-emerald-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 [&::-webkit-details-marker]:hidden">
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-emerald-600 text-sm font-extrabold text-white">@if($clientAccount['avatar'] !== '')<img class="h-full w-full object-cover" src="{{ $clientAccount['avatar'] }}" alt="{{ $clientAccount['name'] }}" referrerpolicy="no-referrer">@else{{ $clientAccount['initial'] }}@endif</span>
                            <span class="hidden max-w-32 truncate text-xs font-bold text-slate-900 sm:block">{{ $clientAccount['name'] }}</span><i class="bx bx-chevron-down text-lg text-slate-400" aria-hidden="true"></i>
                        </summary>
                        <div class="absolute right-0 top-full z-[60] mt-2 w-64 overflow-hidden rounded-[5px] border border-slate-200 bg-white shadow-xl">
                            <div class="border-b border-slate-100 bg-slate-50 px-4 py-3"><p class="truncate text-sm font-bold">{{ $clientAccount['name'] }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $clientAccount['email'] }}</p></div>
                            <nav class="grid gap-1 p-2" aria-label="Menu tài khoản"><a class="rounded-[5px] px-3 py-2.5 text-sm font-semibold hover:bg-emerald-50" href="{{ route('account.index') }}">Thông tin tài khoản</a>@if($clientAccount['role'] === 'admin')<a class="rounded-[5px] px-3 py-2.5 text-sm font-semibold hover:bg-emerald-50" href="{{ route('admin.spa') }}">Trang quản trị</a>@endif<a class="rounded-[5px] px-3 py-2.5 text-sm font-semibold hover:bg-emerald-50" href="{{ route('content.contact') }}">Liên hệ hỗ trợ</a></nav>
                            <form class="border-t border-slate-100 p-2" method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-[5px] px-3 py-2.5 text-left text-sm font-semibold text-rose-600 hover:bg-rose-50" type="submit">Đăng xuất</button></form>
                        </div>
                    </details>
                @else
                    <a class="client-button-secondary gap-2" href="{{ route('auth.login') }}"><i class="bx bx-user text-lg" aria-hidden="true"></i><span class="hidden sm:inline">Đăng nhập</span><span class="sm:hidden">Tài khoản</span></a>
                    <a class="client-button hidden gap-2 lg:inline-flex" href="{{ route('auth.register') }}"><i class="bx bx-user-plus text-lg" aria-hidden="true"></i>Đăng ký</a>
                @endauth
                <details class="relative lg:hidden" data-client-menu>
                    <summary class="grid h-11 w-11 cursor-pointer list-none place-items-center rounded-[5px] border border-slate-200 text-2xl text-slate-700 [&::-webkit-details-marker]:hidden" aria-label="Menu điều hướng"><i class="bx bx-menu" aria-hidden="true"></i></summary>
                    <nav class="absolute right-0 top-full z-[60] mt-2 grid w-64 gap-1 rounded-[5px] border border-slate-200 bg-white p-2 shadow-xl" aria-label="Điều hướng di động">
                        @foreach($clientLinks as $link)<a class="flex items-center gap-3 rounded-[5px] px-3 py-3 text-sm font-semibold hover:bg-emerald-50" href="{{ $link['url'] }}" @if($link['opens_tools'] ?? false) data-client-tools-open role="button" aria-haspopup="dialog" aria-controls="client-tools-modal" @endif><i class="bx {{ $link['icon'] }} text-lg" aria-hidden="true"></i>{{ $link['label'] }}</a>@endforeach
                        <a class="rounded-[5px] px-3 py-3 text-sm font-semibold hover:bg-emerald-50" href="{{ route('content.guide') }}">Hướng dẫn</a><a class="rounded-[5px] px-3 py-3 text-sm font-semibold hover:bg-emerald-50" href="{{ route('content.contact') }}">Liên hệ</a>
                    </nav>
                </details>
            </div>
        </div>
        @if(request()->routeIs('seo.*') && ($navigationCategories ?? collect())->isNotEmpty())
            <nav class="client-container flex gap-4 overflow-x-auto pb-3 text-sm text-emerald-700" aria-label="Chủ đề tin tức">@foreach($navigationCategories as $navigationCategory)<a class="shrink-0" href="{{ route('seo.category', $navigationCategory->slug) }}">{{ $navigationCategory->name }}</a>@endforeach</nav>
        @endif
    </header>
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_18px_rgba(15,23,42,0.08)] backdrop-blur lg:hidden" aria-label="Điều hướng nhanh trên di động" data-mobile-bottom-nav>
        <div class="mx-auto grid h-16 max-w-lg grid-cols-4">
            @foreach($clientLinks as $link)<a @class(['flex min-w-0 flex-col items-center justify-center gap-1 px-1 text-[11px] font-bold transition hover:text-emerald-700', 'text-emerald-700' => $link['active'], 'text-slate-500' => ! $link['active']]) href="{{ $link['url'] }}" @if($link['opens_tools'] ?? false) data-client-tools-open role="button" aria-haspopup="dialog" aria-controls="client-tools-modal" @endif><i class="bx {{ $link['icon'] }} text-xl" aria-hidden="true"></i><span class="truncate">{{ $link['label'] === 'Thông báo game' ? 'Thông báo' : $link['label'] }}</span></a>@endforeach
            <a class="flex min-w-0 flex-col items-center justify-center gap-1 px-1 text-[11px] font-bold text-slate-500 hover:text-emerald-700" href="{{ auth()->check() ? route('account.index') : route('auth.login') }}"><i class="bx bx-user-circle text-xl" aria-hidden="true"></i><span>Tài khoản</span></a>
        </div>
    </nav>
    <main id="main-content" class="client-page-scale min-w-0 focus:outline-none" tabindex="-1" data-page-enter>
        @include('client.partials.flash')
        @yield('content')
    </main>
    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="client-container grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">@if($headerLogo !== '')<img src="{{ $headerLogo }}" alt="{{ $siteName }}" class="h-auto w-36 object-contain object-left">@else<p class="font-extrabold text-slate-950">{{ $siteName }}</p>@endif<p class="mt-3 max-w-md text-sm leading-6 text-slate-600">Công cụ hỗ trợ, thông báo game, tin tức và hướng dẫn dành cho cộng đồng Ngọc Rồng Online.</p><a class="mt-3 inline-flex items-center gap-2 text-sm font-bold text-emerald-700" href="https://napcarot.com/community"><i class="bx bx-group text-lg" aria-hidden="true"></i>Tham gia cộng đồng</a></div>
            <nav class="grid content-start gap-3 text-sm text-slate-600" aria-label="Khám phá"><p class="font-extrabold text-slate-950">Khám phá</p><a href="{{ route('home') }}#cong-cu">Công cụ hỗ trợ</a><a href="{{ route('nro.notifies.page') }}">Thông báo game</a><a href="{{ route('seo.index') }}">Tin tức & hướng dẫn</a></nav>
            <nav class="grid content-start gap-3 text-sm text-slate-600" aria-label="Thông tin website"><p class="font-extrabold text-slate-950">Thông tin</p><a href="{{ route('content.about') }}">Giới thiệu</a><a href="{{ route('content.contact') }}">Liên hệ</a><a href="{{ route('content.terms') }}">Điều khoản sử dụng</a><a href="{{ route('content.privacy') }}">Chính sách bảo mật</a></nav>
        </div>
        <div class="border-t border-slate-100"><div class="client-container py-4 text-xs text-slate-500">© {{ now()->year }} {{ $siteName }}. Công cụ dành cho cộng đồng NRO.</div></div>
    </footer>
    @if ($customScript !== '')
        <!-- custom-script -->
        {!! $customScript !!}
        <!-- /custom-script -->
    @endif
    <x-client.tools-modal />
    @vite('resources/js/client.js')
    @if (($customCodeAssets['js'] ?? false) && ! request()->routeIs(['auth.*', 'password.*', 'verification.*']))
        <script src="{{ route('site_custom.js') }}" defer data-site-custom-js></script>
    @endif
    @stack('scripts')
</body>
</html>
