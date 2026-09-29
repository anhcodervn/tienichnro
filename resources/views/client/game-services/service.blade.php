@extends('client.layouts.app')

@section('document_title', $gameService->name.' - Dịch vụ game '.$game->name)
@section('description', filled($gameService->description) ? $gameService->description : 'Thông tin và bảng giá '.$gameService->name.' cho game '.$game->name.'.')
@section('canonical', route('game-services.service', ['game' => $game, 'gameService' => $gameService]))
@if ($gameService->background_image)
    @section('image', $gameService->background_image)
    @section('image_alt', $gameService->name.' - '.$game->name)
@endif

@section('content')
    @php
        $selectedPackageId = (int) old('package_id', $gameService->packages->first()?->id);
        $statusLabels = [
            'pending' => ['label' => 'Chờ tiếp nhận', 'class' => 'bg-amber-50 text-amber-700'],
            'processing' => ['label' => 'Đang xử lý', 'class' => 'bg-sky-50 text-sky-700'],
            'completed' => ['label' => 'Hoàn thành', 'class' => 'bg-emerald-50 text-emerald-700'],
            'failed' => ['label' => 'Thất bại', 'class' => 'bg-rose-50 text-rose-700'],
            'cancelled' => ['label' => 'Đã hủy', 'class' => 'bg-slate-100 text-slate-600'],
        ];
    @endphp

    <div class="client-container grid gap-10 py-6 sm:py-10">
        <header class="grid gap-4" data-game-service-heading>
            <nav class="flex min-w-0 items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                <a class="shrink-0 font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
                <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
                <a class="truncate font-semibold hover:text-emerald-700" href="{{ route('game-services.show', ['game' => $game]) }}">Dịch vụ {{ $game->name }}</a>
                <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
                <span class="truncate font-bold text-slate-800" aria-current="page">{{ $gameService->name }}</span>
            </nav>
            <div>
                <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">{{ $game->name }}</p>
                <h1 class="mt-2 break-words text-3xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ $gameService->name }}</h1>
            </div>
        </header>

        <section class="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.82fr)]" aria-label="Thông tin và đặt dịch vụ">
            <article class="min-w-0">
                <p class="text-sm font-bold text-emerald-700">Thông tin dịch vụ</p>
                <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Mô tả về dịch vụ</h2>
                @if (filled($gameService->description))
                    <div class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-slate-600 sm:text-base">{{ $gameService->description }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-500">Thông tin chi tiết về dịch vụ đang được cập nhật.</p>
                @endif

                <div class="mt-7 grid gap-3 sm:grid-cols-2">
                    @foreach ($gameService->packages as $package)
                        @php
                            $price = $package->prices->first();
                        @endphp
                        <article class="rounded-xl border border-slate-200 p-4">
                            <h3 class="font-extrabold text-slate-900">{{ $package->name }}</h3>
                            @if (filled($package->description))
                                <p class="mt-1 text-sm leading-6 text-slate-500">{{ $package->description }}</p>
                            @endif
                            <p class="mt-3 text-xl font-extrabold text-amber-700">{{ number_format($price->price, 0, ',', '.') }}đ</p>
                        </article>
                    @endforeach
                </div>
            </article>

            <aside class="client-card p-5 sm:p-6 lg:sticky lg:top-24" data-game-service-order-panel>
                <p class="text-sm font-bold text-emerald-700">Đặt dịch vụ</p>
                <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Tạo đơn mới</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Chọn gói và nhập đúng thông tin để cộng tác viên xử lý đơn.</p>

                @if ($gameService->packages->isEmpty() || $gameService->servers->isEmpty())
                    <div class="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                        Dịch vụ chưa đủ gói hoặc máy chủ đang hoạt động để nhận đơn.
                    </div>
                @else
                    <form class="mt-5 grid gap-4" method="POST" action="{{ route('game-services.orders.store', ['game' => $game, 'gameService' => $gameService]) }}" data-game-service-order-form>
                        @csrf
                        <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                            Gói dịch vụ
                            <select name="package_id" required class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" data-game-service-package-select>
                                @foreach ($gameService->packages as $package)
                                    @php
                                        $price = $package->prices->first();
                                    @endphp
                                    <option value="{{ $package->id }}" data-price="{{ $price->price }}" data-quantity-enabled="{{ $price->quantity_enabled ? '1' : '0' }}" data-min-quantity="{{ $price->quantity_enabled ? $price->min_quantity : 1 }}" data-max-quantity="{{ $price->quantity_enabled ? $price->max_quantity : 1 }}" @selected($selectedPackageId === $package->id)>{{ $package->name }} · {{ number_format($price->price, 0, ',', '.') }}đ</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                            Máy chủ
                            <select name="server_id" required class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                <option value="">Chọn máy chủ</option>
                                @foreach ($gameService->servers as $server)
                                    <option value="{{ $server->id }}" @selected((int) old('server_id') === $server->id)>{{ $server->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        @foreach ($gameService->payload_fields ?? [] as $field)
                            @php
                                $fieldKey = (string) ($field['key'] ?? '');
                                $fieldType = (string) ($field['type'] ?? 'text');
                                $fieldRequired = (bool) ($field['required'] ?? false);
                            @endphp
                            @if ($fieldKey !== '')
                                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                    <span>{{ $field['label'] ?? $fieldKey }} @if (!$fieldRequired)<small class="font-normal text-slate-400">(không bắt buộc)</small>@endif</span>
                                    @if ($fieldType === 'select')
                                        <select name="payload[{{ $fieldKey }}]" @required($fieldRequired) class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            <option value="">{{ $field['placeholder'] ?? 'Chọn thông tin' }}</option>
                                            @foreach ($field['options'] ?? [] as $option)
                                                <option value="{{ $option['value'] }}" @selected(old('payload.'.$fieldKey) === (string) $option['value'])>{{ $option['text'] }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input name="payload[{{ $fieldKey }}]" type="{{ in_array($fieldType, ['number', 'password'], true) ? $fieldType : 'text' }}" @required($fieldRequired) @if ($fieldType !== 'password') value="{{ old('payload.'.$fieldKey) }}" @endif @if ($fieldType === 'number' && is_numeric($field['min'] ?? null)) min="{{ $field['min'] }}" @endif @if ($fieldType === 'number' && is_numeric($field['max'] ?? null)) max="{{ $field['max'] }}" @endif @if ($fieldType === 'number' && is_numeric($field['step'] ?? null)) step="{{ $field['step'] }}" @endif placeholder="{{ $field['placeholder'] ?? '' }}" class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    @endif
                                </label>
                            @endif
                        @endforeach

                        @guest
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                Email nhận thông tin đơn
                                <input name="email" type="email" required value="{{ old('email') }}" autocomplete="email" class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="email@example.com">
                            </label>
                        @endguest

                        <label class="grid gap-1.5 text-sm font-bold text-slate-700" data-game-service-quantity-field>
                            Số lượng
                            <input name="quantity" type="number" required value="{{ old('quantity', 1) }}" min="1" max="1" class="min-h-11 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" data-game-service-quantity>
                            <small class="font-normal text-slate-500" data-game-service-quantity-help></small>
                        </label>

                        <div class="flex items-center justify-between gap-4 rounded-xl bg-amber-50 px-4 py-3">
                            <span class="text-sm font-bold text-amber-900">Tổng thanh toán</span>
                            <strong class="text-xl text-amber-700" data-game-service-total>0đ</strong>
                        </div>

                        <button type="submit" class="client-button min-h-12 w-full">Đặt đơn dịch vụ</button>
                    </form>
                @endif
            </aside>
        </section>

        <section aria-labelledby="recent-game-service-orders">
            <p class="text-sm font-bold text-emerald-700">Hoạt động gần đây</p>
            <h2 id="recent-game-service-orders" class="mt-1 text-2xl font-extrabold text-slate-950">5 đơn mới nhất</h2>
            <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
                @forelse ($recentOrders as $order)
                    @php
                        $status = $statusLabels[$order->status] ?? ['label' => 'Đang cập nhật', 'class' => 'bg-slate-100 text-slate-600'];
                    @endphp
                    <div class="grid gap-2 border-b border-slate-100 px-4 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-5">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">{{ $order->package_name }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $order->server_name }} · Số lượng {{ $order->quantity }}</p>
                        </div>
                        <time class="text-xs font-semibold text-slate-500" datetime="{{ $order->created_at?->toISOString() }}">{{ $order->created_at?->diffForHumans() }}</time>
                        <span class="w-fit rounded-full px-2.5 py-1 text-xs font-bold {{ $status['class'] }}">{{ $status['label'] }}</span>
                    </div>
                @empty
                    <p class="p-6 text-center text-sm font-semibold text-slate-500">Chưa có đơn dịch vụ nào.</p>
                @endforelse
            </div>
        </section>

        @if (filled($seoContentHtml->toHtml()))
            <article class="min-w-0" aria-labelledby="game-service-seo-title">
                <h2 id="game-service-seo-title" class="text-2xl font-extrabold text-slate-950">Thông tin chi tiết {{ $gameService->name }}</h2>
                <div class="article-content mt-5 min-w-0 max-w-full break-words" data-client-image-viewer>{!! $seoContentHtml !!}</div>
            </article>
        @endif

        @if ($seoFaqs->isNotEmpty())
            <section aria-labelledby="game-service-faq-title">
                <p class="text-sm font-bold text-emerald-700">Giải đáp nhanh</p>
                <h2 id="game-service-faq-title" class="mt-1 text-2xl font-extrabold text-slate-950">Câu hỏi thường gặp</h2>
                <div class="mt-5 grid gap-3">
                    @foreach ($seoFaqs as $faq)
                        <details class="group rounded-xl border border-slate-200 bg-white px-5 py-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-slate-900">
                                <span>{{ $faq['question'] }}</span>
                                <i class="bx bx-chevron-down text-xl text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <p class="mt-3 border-t border-slate-100 pt-3 text-sm leading-7 text-slate-600">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
