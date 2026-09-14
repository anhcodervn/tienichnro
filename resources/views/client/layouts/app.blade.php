<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $settings = $systemSettings ?? [];
        $siteName = $settings['site_name'] ?? config('app.name', 'Nạp Carot');
        $title = trim($__env->yieldContent('title')) ?: ($settings['meta_title'] ?? $siteName);
        $description = trim($__env->yieldContent('description')) ?: ($settings['meta_description'] ?? $settings['site_description'] ?? 'Nạp Carot game Teamobi nhanh chóng, minh bạch.');
        $keywords = trim($__env->yieldContent('keywords'));
        $canonical = trim($__env->yieldContent('canonical')) ?: url()->current();
        $favicon = trim((string) ($settings['favicon'] ?? ''));
        $headerLogo = trim((string) ($settings['dark_logo'] ?? '')) ?: trim((string) ($settings['light_logo'] ?? ''));
        $shareImage = trim($__env->yieldContent('image')) ?: trim((string) ($settings['og_image'] ?? ''));
        $shareImage = $shareImage !== '' && ! \Illuminate\Support\Str::startsWith($shareImage, ['http://', 'https://'])
            ? url($shareImage)
            : $shareImage;
        $gameServiceItems = is_array($settings['game_service_items'] ?? null) ? $settings['game_service_items'] : [];
        $showGameServiceMenu = ($settings['game_service_enabled'] ?? false) === true && $gameServiceItems !== [];
        $showAgencyWebsite = app(\App\Support\TenantContext::class)->isActive() && \App\Utils\Site::isMain();
        $explicitDocumentTitle = trim($__env->yieldContent('document_title'));
        $documentTitle = $explicitDocumentTitle !== ''
            ? $explicitDocumentTitle
            : ($title === $siteName || \Illuminate\Support\Str::endsWith($title, ' | '.$siteName)
                ? $title
                : $title.' | '.$siteName);
        $orderHistoryUrl = auth()->check() ? route('account.orders.index') : route('orders.lookup');
        $orderHistoryActive = request()->routeIs(['orders.*', 'account.orders.*']);
        $exploreActive = request()->routeIs(['client.affiliate.spa', 'seo.*', 'content.guide', 'content.contact']);
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
<body data-authenticated="{{ auth()->check() ? 'true' : 'false' }}" data-order-lookup-url="{{ route('orders.lookup') }}" data-order-detail-url-template="{{ route('orders.details', ['order' => '__ORDER__']) }}">
    @if ($gtmId !== '')
        <noscript data-site-gtm-noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @if ($metaPixelId !== '')
        <noscript data-site-meta-pixel-noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ urlencode($metaPixelId) }}&amp;ev=PageView&amp;noscript=1" alt=""></noscript>
    @endif
    <a href="#main-content" class="sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-[5px] focus:bg-slate-950 focus:px-4 focus:py-3 focus:text-white">Bỏ qua điều hướng</a>
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="client-container flex min-h-[4.5rem] items-center justify-between gap-3 sm:min-h-20">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 rounded-[5px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-client-home>
                @if ($headerLogo !== '')
                    <img src="{{ $headerLogo }}" alt="{{ $siteName }}" class="h-auto w-32 shrink-0 object-contain object-left sm:w-40">
                @else
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] bg-emerald-600 font-extrabold text-white">C</span>
                    <span class="truncate font-extrabold tracking-tight text-slate-950">{{ $siteName }}</span>
                @endif
            </a>
            <nav class="hidden min-w-0 items-center gap-2 text-sm font-semibold text-slate-700 lg:flex xl:gap-4" aria-label="Điều hướng chính">
                <a @class(['inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-[5px] px-2', 'text-emerald-700' => request()->routeIs('wallet.deposit.*'), 'hover:text-emerald-700' => ! request()->routeIs('wallet.deposit.*')]) href="{{ route('wallet.deposit.index') }}" @if (request()->routeIs('wallet.deposit.*')) aria-current="page" @endif><i class="bx bx-wallet-alt text-lg" aria-hidden="true"></i><span>Nạp tiền</span></a>
                <a @class(['inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-[5px] px-2', 'text-emerald-700' => $orderHistoryActive, 'hover:text-emerald-700' => ! $orderHistoryActive]) href="{{ $orderHistoryUrl }}" @if ($orderHistoryActive) aria-current="page" @endif><i class="bx bx-history text-lg" aria-hidden="true"></i><span>Đơn hàng</span></a>
                @if ($showGameServiceMenu)
                    <details class="group relative shrink-0" data-desktop-nav-menu data-game-service-menu>
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1.5 whitespace-nowrap rounded-[5px] px-2 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 [&::-webkit-details-marker]:hidden">
                            <i class="bx bx-game text-lg" aria-hidden="true"></i>
                            <span>Dịch vụ game</span>
                            <i class="bx bx-chevron-down text-base transition-transform group-open:rotate-180" aria-hidden="true"></i>
                        </summary>
                        <div class="absolute left-1/2 top-full z-50 mt-2 w-72 -translate-x-1/2 rounded-[10px] border border-slate-200 bg-white p-2 shadow-xl">
                            <p class="px-3 pb-2 pt-1 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Chọn dịch vụ</p>
                            <div class="grid gap-1">
                                @foreach ($gameServiceItems as $gameServiceItem)
                                    <a class="flex items-center justify-between gap-3 rounded-[8px] px-3 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-700" href="{{ $gameServiceItem['url'] }}" data-game-service-link>
                                        <span>{{ $gameServiceItem['label'] }}</span>
                                        <i class="bx bx-right-arrow-alt text-lg text-slate-400" aria-hidden="true"></i>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif
                <details class="group relative shrink-0" data-desktop-nav-menu>
                    <summary @class(['flex min-h-11 cursor-pointer list-none items-center gap-1.5 whitespace-nowrap rounded-[5px] px-2 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 [&::-webkit-details-marker]:hidden', 'text-emerald-700' => $exploreActive])>
                        <i class="bx bx-compass text-lg" aria-hidden="true"></i>
                        <span>Khám phá</span>
                        <i class="bx bx-chevron-down text-base transition-transform group-open:rotate-180" aria-hidden="true"></i>
                    </summary>
                    <div class="absolute right-0 top-full z-50 mt-2 w-64 overflow-hidden rounded-[10px] border border-slate-200 bg-white p-2 shadow-xl">
                        <div class="grid gap-1">
                            @if ($affiliateEnabled ?? false)
                                <a @class(['flex min-h-11 items-center gap-3 rounded-[8px] px-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('client.affiliate.spa'), 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-700' => ! request()->routeIs('client.affiliate.spa')]) href="{{ route('client.affiliate.spa') }}" @if (request()->routeIs('client.affiliate.spa')) aria-current="page" @endif><i class="bx bx-group text-lg" aria-hidden="true"></i><span>Cộng tác viên</span></a>
                            @endif
                            <a @class(['flex min-h-11 items-center gap-3 rounded-[8px] px-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('seo.*'), 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-700' => ! request()->routeIs('seo.*')]) href="{{ route('seo.index') }}" @if (request()->routeIs('seo.*')) aria-current="page" @endif><i class="bx bx-news text-lg" aria-hidden="true"></i><span>Bài viết</span></a>
                            <a @class(['flex min-h-11 items-center gap-3 rounded-[8px] px-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('content.guide'), 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-700' => ! request()->routeIs('content.guide')]) href="{{ route('content.guide') }}" @if (request()->routeIs('content.guide')) aria-current="page" @endif><i class="bx bx-book-open text-lg" aria-hidden="true"></i><span>Hướng dẫn</span></a>
                            <a @class(['flex min-h-11 items-center gap-3 rounded-[8px] px-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('content.contact'), 'text-slate-700 hover:bg-emerald-50 hover:text-emerald-700' => ! request()->routeIs('content.contact')]) href="{{ route('content.contact') }}" @if (request()->routeIs('content.contact')) aria-current="page" @endif>
                                <svg class="h-[18px] w-[18px] shrink-0" data-support-icon aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z" />
                                    <path d="M8 9h8M8 13h5" />
                                </svg>
                                <span>Liên hệ hỗ trợ</span>
                            </a>
                        </div>
                    </div>
                </details>
            </nav>
            <div class="hidden items-center gap-2 lg:flex">
                @auth
                    <div class="relative" data-account-menu>
                        <button
                            type="button"
                            class="flex min-h-12 w-[15.5rem] items-center gap-3 rounded-[5px] border border-slate-200 bg-white px-3 py-2 text-left shadow-sm transition duration-200 hover:border-slate-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                            data-account-menu-toggle
                            aria-controls="client-account-menu"
                            aria-expanded="false"
                            aria-haspopup="true"
                        >
                            <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-950 text-sm font-extrabold text-white">
                                @if ($clientAccount['avatar'] !== '')
                                    <img class="h-full w-full object-cover" src="{{ $clientAccount['avatar'] }}" alt="Ảnh đại diện của {{ $clientAccount['name'] }}" referrerpolicy="no-referrer">
                                @else
                                    {{ $clientAccount['initial'] }}
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold leading-5 text-slate-900">{{ $clientAccount['email'] }}</span>
                                <span class="block truncate text-xs font-semibold tabular-nums text-emerald-700" data-header-wallet-balance>{{ number_format((float) $clientAccount['balance'], 0, ',', '.') }}đ</span>
                            </span>
                            <i class="bx bx-chevron-down shrink-0 text-xl text-slate-400 transition-transform duration-200" data-account-menu-chevron aria-hidden="true"></i>
                        </button>

                        <div id="client-account-menu" class="absolute right-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-[5px] border border-slate-200 bg-white shadow-xl" data-account-menu-panel hidden>
                            <div class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                                <p class="truncate text-sm font-bold text-slate-950">{{ $clientAccount['name'] }}</p>
                                <p class="mt-0.5 truncate text-xs text-slate-500">{{ $clientAccount['email'] }}</p>
                                <p class="mt-2 text-xs text-slate-500">Số dư khả dụng</p>
                                <p class="text-lg font-extrabold tabular-nums text-emerald-700" data-account-menu-balance>{{ number_format((float) $clientAccount['balance'], 0, ',', '.') }}đ</p>
                            </div>
                            <nav class="grid gap-1 p-2" aria-label="Menu tài khoản">
                                <a class="flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ route('account.index') }}"><i class="bx bx-user-circle text-lg" aria-hidden="true"></i>Tổng quan tài khoản</a>
                                <a class="flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ route('wallet.deposit.index') }}"><i class="bx bx-wallet-alt text-lg" aria-hidden="true"></i>Nạp tiền</a>
                                <a class="flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ route('account.orders.index') }}"><i class="bx bx-receipt text-lg" aria-hidden="true"></i>Lịch sử đơn hàng</a>
                                @if ($affiliateEnabled ?? false)
                                    <a class="flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="{{ route('client.affiliate.spa') }}"><i class="bx bx-group text-lg" aria-hidden="true"></i>Cộng tác viên</a>
                                @endif
                                <a data-account-support-link @class(['flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600', 'bg-emerald-50 text-emerald-700' => request()->routeIs('client.support.chat'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('client.support.chat')]) href="{{ route('client.support.chat') }}"><i class="bx bx-message-circle-dots text-lg" aria-hidden="true"></i>Liên hệ hỗ trợ</a>
                                @if ($showAgencyWebsite)
                                    <a @class(['flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600', 'bg-emerald-50 text-emerald-700' => request()->routeIs('client.agency.website'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('client.agency.website')]) href="{{ route('client.agency.website') }}"><i class="bx bx-store-alt text-lg" aria-hidden="true"></i>Tạo website đại lý</a>
                                @endif
                                <a @class(['flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600', 'bg-emerald-50 text-emerald-700' => request()->routeIs('client.agency.api'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('client.agency.api')]) href="{{ route('client.agency.api') }}"><i class="bx bx-code text-lg" aria-hidden="true"></i>Kết nối API</a>
                                @if ($clientAccount['role'] === 'admin')
                                    <a class="flex min-h-10 items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" href="/admin"><i class="bx bx-shield text-lg" aria-hidden="true"></i>Trang quản trị</a>
                                @endif
                            </nav>
                            <form class="border-t border-slate-100 p-2" method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex min-h-10 w-full items-center gap-3 rounded-[5px] px-3 text-sm font-semibold text-rose-600 transition hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500"><i class="bx bx-log-out text-lg" aria-hidden="true"></i>Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a class="client-button-secondary gap-2" href="{{ route('auth.login') }}"><i class="bx bx-user text-lg" aria-hidden="true"></i>Đăng nhập</a>
                    <a class="client-button gap-2" href="{{ route('auth.register') }}"><i class="bx bx-user-plus text-lg" aria-hidden="true"></i>Đăng ký</a>
                @endauth
            </div>
            <button
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-[5px] border border-slate-300 bg-white text-slate-700 transition hover:border-emerald-400 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 lg:hidden"
                data-menu-toggle
                aria-controls="mobile-menu"
                aria-expanded="false"
                aria-haspopup="dialog"
                aria-label="Mở menu"
            >
                <svg class="h-6 w-6" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <g data-menu-icon-open>
                        <path d="M4 6h16M4 12h16M4 18h16" />
                    </g>
                    <g data-menu-icon-close>
                        <path d="M6 6l12 12M18 6 6 18" />
                    </g>
                </svg>
            </button>
        </div>
    </header>

    <div id="mobile-menu" class="fixed inset-0 z-50 lg:hidden" data-mobile-menu aria-hidden="true" hidden>
        <button type="button" class="absolute inset-0 bg-slate-950/45 backdrop-blur-[1px]" data-menu-close data-menu-backdrop tabindex="-1" aria-label="Đóng menu"></button>

        <aside
            class="absolute inset-y-0 right-0 flex h-dvh w-[min(22rem,calc(100%-2rem))] flex-col border-l border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] pt-[env(safe-area-inset-top)] shadow-2xl"
            data-menu-panel
            role="dialog"
            aria-modal="true"
            aria-labelledby="mobile-menu-title"
            tabindex="-1"
        >
            <div class="flex min-h-16 items-center justify-between gap-3 border-b border-slate-200 px-4">
                <div class="min-w-0">
                    <p id="mobile-menu-title" class="truncate font-extrabold text-slate-950">{{ $siteName }}</p>
                    <p class="text-xs text-slate-500">Điều hướng nhanh</p>
                </div>
                <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] border border-slate-300 text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-menu-close aria-label="Đóng menu">
                    <svg class="h-5 w-5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            <nav class="min-h-0 flex-1 overscroll-contain overflow-y-auto px-3 py-4" aria-label="Điều hướng di động">
                <div class="grid gap-1">
                    <a data-menu-item @class(['flex items-center gap-3 rounded-[5px] px-4 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('home'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('home')]) href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><i class="bx bx-home-alt-2 text-xl" aria-hidden="true"></i><span>Trang chủ</span></a>
                    <a data-menu-item @class(['flex items-center gap-3 rounded-[5px] px-4 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('wallet.deposit.*'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('wallet.deposit.*')]) href="{{ route('wallet.deposit.index') }}" @if (request()->routeIs('wallet.deposit.*')) aria-current="page" @endif><i class="bx bx-wallet-alt text-xl" aria-hidden="true"></i><span>Nạp tiền</span></a>
                    <a data-menu-item @class(['flex items-center gap-3 rounded-[5px] px-4 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => $orderHistoryActive, 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! $orderHistoryActive]) href="{{ $orderHistoryUrl }}" @if ($orderHistoryActive) aria-current="page" @endif><i class="bx bx-history text-xl" aria-hidden="true"></i><span>Lịch sử đơn hàng</span></a>
                    @if ($affiliateEnabled ?? false)
                        <a data-menu-item @class(['flex items-center gap-3 rounded-[5px] px-4 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('client.affiliate.spa'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('client.affiliate.spa')]) href="{{ route('client.affiliate.spa') }}" @if (request()->routeIs('client.affiliate.spa')) aria-current="page" @endif><i class="bx bx-group text-xl" aria-hidden="true"></i><span>Cộng tác viên</span></a>
                    @endif
                    <a data-menu-item @class(['flex items-center gap-3 rounded-[5px] px-4 py-3 text-sm font-semibold transition', 'bg-emerald-50 text-emerald-700' => request()->routeIs('seo.*'), 'text-slate-700 hover:bg-slate-50 hover:text-emerald-700' => ! request()->routeIs('seo.*')]) href="{{ route('seo.index') }}" @if (request()->routeIs('seo.*')) aria-current="page" @endif><i class="bx bx-news text-xl" aria-hidden="true"></i><span>Bài viết</span></a>
                    @if ($showGameServiceMenu)
                        <details class="group rounded-[8px] border border-slate-200 bg-slate-50" data-game-service-menu>
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-[8px] px-4 py-3 text-sm font-semibold text-slate-700 transition hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 [&::-webkit-details-marker]:hidden">
                                <i class="bx bx-game text-xl" aria-hidden="true"></i>
                                <span class="flex-1">Dịch vụ game</span>
                                <i class="bx bx-chevron-down text-xl transition-transform group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <div class="grid gap-1 border-t border-slate-200 bg-white p-2">
                                @foreach ($gameServiceItems as $gameServiceItem)
                                    <a data-menu-item class="flex items-center gap-3 rounded-[6px] px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-emerald-50 hover:text-emerald-700" href="{{ $gameServiceItem['url'] }}" data-game-service-link>
                                        <i class="bx bx-subdirectory-right text-lg text-slate-400" aria-hidden="true"></i>
                                        <span>{{ $gameServiceItem['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </nav>

            <div class="border-t border-slate-200 bg-slate-50 p-4">
                @auth
                    <div class="mb-3 flex min-w-0 items-center gap-3 rounded-[5px] border border-slate-200 bg-white p-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full bg-slate-950 text-sm font-extrabold text-white">
                            @if ($clientAccount['avatar'] !== '')
                                <img class="h-full w-full object-cover" src="{{ $clientAccount['avatar'] }}" alt="Ảnh đại diện của {{ $clientAccount['name'] }}" referrerpolicy="no-referrer">
                            @else
                                {{ $clientAccount['initial'] }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-950">{{ $clientAccount['email'] }}</p>
                            <p class="truncate text-xs font-semibold tabular-nums text-emerald-700">{{ number_format((float) $clientAccount['balance'], 0, ',', '.') }}đ</p>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <a data-account-support-link class="client-button-secondary w-full justify-start bg-white" href="{{ route('client.support.chat') }}"><i class="bx bx-message-circle-dots text-lg" aria-hidden="true"></i>Liên hệ hỗ trợ</a>
                        @if (auth()->user()->role === 'admin')
                            <a class="client-button-secondary w-full bg-white" href="/admin">Trang quản trị</a>
                        @endif
                        @if ($showAgencyWebsite)
                            <a class="client-button-secondary w-full justify-start bg-white" href="{{ route('client.agency.website') }}"><i class="bx bx-store-alt text-lg" aria-hidden="true"></i>Tạo website đại lý</a>
                        @endif
                        <a class="client-button-secondary w-full justify-start bg-white" href="{{ route('client.agency.api') }}"><i class="bx bx-code text-lg" aria-hidden="true"></i>Kết nối API</a>
                        <a class="client-button-secondary w-full bg-white" href="{{ route('account.index') }}">Tài khoản</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="client-button-secondary w-full bg-white">Đăng xuất</button>
                        </form>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <a class="client-button-secondary bg-white" href="{{ route('auth.login') }}">Đăng nhập</a>
                        <a class="client-button" href="{{ route('auth.register') }}">Đăng ký</a>
                    </div>
                @endauth
            </div>
        </aside>
    </div>

    <main id="main-content" class="min-w-0 focus:outline-none" tabindex="-1" data-page-enter>
        @include('client.partials.flash')
        @yield('content')
    </main>

    @unless (request()->routeIs(['content.contact', 'client.support.chat']))
        <x-client.floating-support :raised="request()->routeIs('wallet.deposit.*')" />
    @endunless

    <footer class="mt-16 border-t border-slate-200 bg-white">
        <div class="client-container grid gap-8 py-10 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                @if ($headerLogo !== '')
                    <img src="{{ $headerLogo }}" alt="{{ $siteName }}" class="h-auto w-[15rem] object-contain object-left sm:w-36">
                @else
                    <p class="font-extrabold text-slate-950">{{ $siteName }}</p>
                @endif
                <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">Dịch vụ nạp Carot game Teamobi với trạng thái đơn minh bạch và hỗ trợ rõ ràng.</p>
            </div>
            <div><p class="font-bold">Dịch vụ</p><div class="mt-3 grid gap-2 text-sm text-slate-600"><a href="{{ route('home') }}">Nạp game</a><a href="{{ route('wallet.deposit.index') }}">Nạp tiền</a><a href="{{ $orderHistoryUrl }}">Lịch sử đơn hàng</a></div></div>
            <div><p class="font-bold">Hỗ trợ</p><div class="mt-3 grid gap-2 text-sm text-slate-600"><a href="{{ route('content.guide') }}">Hướng dẫn</a><a href="{{ route('content.contact') }}">Liên hệ</a></div></div>
        </div>
    </footer>
    @vite('resources/js/client.js')
    @if (($customCodeAssets['js'] ?? false) && ! request()->routeIs(['auth.*', 'password.*', 'verification.*']))
        <script src="{{ route('site_custom.js') }}" data-site-custom-js></script>
    @endif
    @if ($customScript !== '')
        <!-- custom-script -->
        {!! $customScript !!}
        <!-- /custom-script -->
    @endif
</body>
</html>
