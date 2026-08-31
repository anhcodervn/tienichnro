<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $profile['title'] }}</title>
    <meta name="description" content="{{ $profile['description'] }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800" rel="stylesheet">
    <x-boxicon />
    @vite('resources/css/client.css')
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-12 sm:px-6" id="main-content">
        <div class="absolute inset-0 -z-20 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.22),_transparent_42%),radial-gradient(circle_at_bottom_right,_rgba(34,211,238,0.18),_transparent_35%)]"></div>
        <div class="absolute left-1/2 top-16 -z-10 h-64 w-64 -translate-x-1/2 rounded-full bg-emerald-400/10 blur-3xl"></div>

        <section class="w-full max-w-xl" aria-labelledby="bio-title">
            <div class="flex flex-col items-center text-center">
                @if ($profile['avatar_url'] !== '')
                    <img class="h-24 w-24 rounded-full border-4 border-white/10 bg-white object-cover shadow-2xl sm:h-28 sm:w-28" src="{{ $profile['avatar_url'] }}" alt="Ảnh đại diện của {{ $profile['title'] }}">
                @else
                    <div class="grid h-24 w-24 place-items-center rounded-full border-4 border-white/10 bg-gradient-to-br from-emerald-400 to-cyan-500 text-3xl font-extrabold text-slate-950 shadow-2xl sm:h-28 sm:w-28" aria-hidden="true">
                        {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($profile['title'], 0, 1)) }}
                    </div>
                @endif

                <p class="mt-6 text-xs font-bold uppercase tracking-[0.28em] text-emerald-300">Kết nối cùng chúng tôi</p>
                <h1 id="bio-title" class="mt-3 text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{{ $profile['title'] }}</h1>
                @if ($profile['description'] !== '')
                    <p class="mt-3 max-w-md text-sm leading-7 text-slate-300 sm:text-base">{{ $profile['description'] }}</p>
                @endif
            </div>

            <div class="mt-8 grid gap-3" data-bio-links>
                @forelse ($links as $link)
                    <a class="group flex min-h-16 items-center justify-between gap-4 rounded-2xl border border-white/10 bg-white/[0.07] px-5 py-3 font-bold text-white shadow-lg shadow-black/10 backdrop-blur transition hover:-translate-y-0.5 hover:border-emerald-300/60 hover:bg-white/[0.12] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300" href="{{ $link['url'] }}" data-bio-link>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-white/10 text-xl text-emerald-300" aria-hidden="true">
                            <i class="{{ $link['icon_class'] }}"></i>
                        </span>
                        <span class="min-w-0 flex-1 text-left">{{ $link['label'] }}</span>
                        <span class="text-lg text-slate-400 transition group-hover:translate-x-1 group-hover:text-white" aria-hidden="true">→</span>
                    </a>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/15 bg-white/[0.04] px-5 py-8 text-center text-sm text-slate-400">
                        Các liên kết đang được cập nhật.
                    </div>
                @endforelse
            </div>

            <div class="mt-8 flex justify-center">
                <a class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-white/15 bg-slate-900/70 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:border-white/30 hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300" href="{{ route('home') }}" data-home-link>
                    <span aria-hidden="true">←</span>
                    Quay về trang chủ
                </a>
            </div>
        </section>
    </main>
</body>
</html>
