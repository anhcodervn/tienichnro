<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $settings = $systemSettings ?? [];
        $siteName = $settings['site_name'] ?? config('app.name', 'Laravel');
        $metaTitle = $settings['meta_title'] ?: $siteName;
        $metaDescription = $settings['meta_description'] ?? '';
        $favicon = $settings['favicon'] ?? '';
    @endphp

    <title>{{ $metaTitle }}</title>
    @if ($metaDescription !== '')
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if ($favicon !== '')
        <link rel="icon" href="{{ $favicon }}">
        <link rel="shortcut icon" href="{{ $favicon }}">
        <link rel="apple-touch-icon" href="{{ $favicon }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700|ibm-plex-sans:400,500,600,700|space-grotesk:500,600,700"
        rel="stylesheet" />

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.ts'])

    <!-- Google Tag Manager -->
    @if (!empty($settings['gtm_id']))
        <script>
            (function(w, d, s, l, i) {
                w[l] = w[l] || [];
                w[l].push({
                    'gtm.start': new Date().getTime(),
                    event: 'gtm.js'
                });
                var f = d.getElementsByTagName(s)[0],
                    j = d.createElement(s),
                    dl = l != 'dataLayer' ? '&l=' + l : '';
                j.async = true;
                j.src =
                    'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
                f.parentNode.insertBefore(j, f);
            })(window, document, 'script', 'dataLayer', '{{ $settings['gtm_id'] }}');
        </script>
    @endif
    <!-- End Google Tag Manager -->

    <!-- Meta Pixel ID -->
    @if (!empty($settings['meta_pixel_id']))
        <!-- Meta Pixel Code -->
        <script>
            ! function(f, b, e, v, n, t, s) {
                if (f.fbq) return;
                n = f.fbq = function() {
                    n.callMethod ?
                        n.callMethod.apply(n, arguments) : n.queue.push(arguments)
                };
                if (!f._fbq) f._fbq = n;
                n.push = n;
                n.loaded = !0;
                n.version = '2.0';
                n.queue = [];
                t = b.createElement(e);
                t.async = !0;
                t.src = v;
                s = b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t, s)
            }(window, document, 'script',
                'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ $settings['meta_pixel_id'] }}');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
                src="https://www.facebook.com/tr?id={{ $settings['meta_pixel_id'] }}&ev=PageView&noscript=1" /></noscript>
        <!-- End Meta Pixel Code -->
    @endif
</head>

<body class="font-sans antialiased">
    <div id="app" data-site-name="{{ $siteName }}"></div>

    <script src="{{ asset('assets/libs/tinymce/tinymce.min.js') }}"></script>

    <!-- Google Tag Manager (noscript) -->
    @if (!empty($settings['gtm_id']))
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ $settings['gtm_id'] }}" height="0"
                width="0" style="display:none;visibility:hidden"></iframe>
        </noscript>
    @endif
    <!-- End Google Tag Manager (noscript) -->

    <!-- custom js -->
    {!! $settings['custom_script'] ?? '' !!}
</body>

</html>
