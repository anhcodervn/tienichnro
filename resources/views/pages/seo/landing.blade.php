@extends('client.layouts.app')
@section('document_title', $landing['title'])
@section('description', $landing['description'])
@section('canonical', $canonicalUrl)
@section('robots', 'index,follow')

@push('head')
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
<section class="border-b border-slate-200 bg-gradient-to-b from-emerald-50/80 to-white">
    <div class="client-container py-8 sm:py-12">
        <nav class="flex items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
            <a class="font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
            <i class="bx bx-chevron-right text-lg text-slate-300" aria-hidden="true"></i>
            <span class="font-bold text-slate-800" aria-current="page">{{ $landing['name'] }}</span>
        </nav>
        <div class="mt-6 max-w-4xl">
            <p class="text-sm font-extrabold uppercase tracking-[0.14em] text-emerald-700">NapCarot</p>
            <h1 class="mt-2 text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">{{ $landing['heading'] }}</h1>
            <p class="mt-5 text-base leading-7 text-slate-700 sm:text-lg">{{ $landing['intro'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @if ($games->count() === 1)
                    <a class="client-button gap-2" href="{{ route('topup.game', $games->first()) }}"><i class="bx bx-bolt text-lg" aria-hidden="true"></i>Nạp {{ $games->first()->name }}</a>
                @else
                    <a class="client-button gap-2" href="{{ route('home') }}#nap-game"><i class="bx bx-bolt text-lg" aria-hidden="true"></i>Đến biểu mẫu nạp</a>
                @endif
                <a class="client-button-secondary gap-2" href="{{ route('pricing') }}"><i class="bx bx-receipt text-lg" aria-hidden="true"></i>Xem bảng giá</a>
            </div>
        </div>
    </div>
</section>

<section class="client-container py-8 sm:py-10">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0">
            <section aria-labelledby="landing-prices-title">
                <p class="text-sm font-bold text-cyan-700">Dữ liệu gói hiện tại</p>
                <h2 id="landing-prices-title" class="mt-1 text-2xl font-extrabold text-slate-950">Bảng giá và gói đang hỗ trợ</h2>
                <p class="mt-3 leading-7 text-slate-600">Giá dưới đây được lấy từ gói đang hoạt động trong hệ thống, không phải nội dung cố định của trang SEO.</p>

                @if ($games->isNotEmpty() && $games->contains(fn ($game) => $game->packages->isNotEmpty()))
                    <div class="mt-5 grid gap-5">
                        @foreach ($games as $game)
                            @if ($game->packages->isNotEmpty())
                                <article class="client-card overflow-hidden">
                                    <header class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                        <h3 class="font-extrabold text-slate-950">{{ $game->name }}</h3>
                                    </header>
                                    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Bảng giá {{ $game->name }}">
                                        <table class="home-price-table w-full">
                                            <thead><tr><th scope="col">Gói nạp</th><th scope="col">Mệnh giá</th><th scope="col">Giá thanh toán</th></tr></thead>
                                            <tbody>
                                                @foreach ($game->packages as $package)
                                                    <tr>
                                                        <th scope="row">{{ $package->name }}</th>
                                                        <td>{{ number_format((int) $package->denomination, 0, ',', '.') }}đ</td>
                                                        <td class="font-bold text-emerald-700">{{ number_format((int) $package->price, 0, ',', '.') }}đ</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </article>
                            @endif
                        @endforeach
                    </div>
                @else
                    <div class="client-card mt-5 p-6 text-sm leading-6 text-slate-600">
                        Game hoặc gói nạp tương ứng hiện chưa được mở bán. Trang này không hiển thị giá giả; vui lòng kiểm tra lại danh sách game đang hoạt động trên trang chủ.
                    </div>
                @endif
            </section>

            <section class="mt-10" aria-labelledby="landing-guides-title">
                <p class="text-sm font-bold text-cyan-700">Kiến thức liên quan</p>
                <h2 id="landing-guides-title" class="mt-1 text-2xl font-extrabold text-slate-950">Hướng dẫn {{ mb_strtolower($landing['name']) }}</h2>
                @if ($relatedPosts->isNotEmpty())
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        @foreach ($relatedPosts as $post)
                            <article class="client-card p-5">
                                <h3 class="font-extrabold leading-6 text-slate-950"><a class="hover:text-emerald-700" href="{{ $post['url'] }}">{{ $post['title'] }}</a></h3>
                                @if ($post['excerpt'])
                                    <p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{{ $post['excerpt'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="client-card mt-5 p-6 text-sm leading-6 text-slate-600">Bài hướng dẫn liên quan đang được biên tập và sẽ chỉ xuất hiện sau khi được admin xuất bản.</div>
                @endif
            </section>
        </div>

        <aside class="min-w-0">
            <section class="client-card p-5 lg:sticky lg:top-24" aria-labelledby="other-games-title">
                <h2 id="other-games-title" class="font-extrabold text-slate-950">Trang nạp game Teamobi</h2>
                <div class="mt-4 grid gap-2">
                    <a class="rounded-[6px] border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ route('seo.landing', ['landingSlug' => 'nap-carot']) }}">Nạp Carot</a>
                    <a class="rounded-[6px] border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 hover:border-emerald-300 hover:text-emerald-700" href="{{ route('seo.landing', ['landingSlug' => 'nap-game-teamobi']) }}">Nạp game Teamobi</a>
                    @foreach ($gameLandings as $gameLanding)
                        <a @class([
                            'rounded-[6px] border px-3 py-2.5 text-sm font-bold',
                            'border-emerald-300 bg-emerald-50 text-emerald-800' => $gameLanding['url'] === $canonicalUrl,
                            'border-slate-200 text-slate-700 hover:border-emerald-300 hover:text-emerald-700' => $gameLanding['url'] !== $canonicalUrl,
                        ]) href="{{ $gameLanding['url'] }}">{{ $gameLanding['name'] }}</a>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>
</section>
@endsection
