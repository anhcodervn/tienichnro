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
    @endphp

    <div class="client-container grid gap-8 py-6 sm:gap-10 sm:py-10">
        <header class="overflow-hidden rounded-[10px] border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-cyan-50 p-5 shadow-sm sm:p-8" data-game-service-heading>
            <nav class="flex min-w-0 items-center gap-1.5 text-sm text-slate-500" aria-label="Breadcrumb">
                <a class="shrink-0 font-semibold hover:text-emerald-700" href="{{ route('home') }}">Trang chủ</a>
                <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
                <a class="truncate font-semibold hover:text-emerald-700" href="{{ route('game-services.show', ['game' => $game]) }}">Dịch vụ {{ $game->name }}</a>
                <i class="bx bx-chevron-right shrink-0 text-lg text-slate-300" aria-hidden="true"></i>
                <span class="truncate font-bold text-slate-800" aria-current="page">{{ $gameService->name }}</span>
            </nav>
            <div class="mt-5 flex items-center gap-4 sm:gap-6">
                @if ($gameService->background_image || $game->image)
                    <img class="size-20 shrink-0 rounded-[10px] border border-white object-cover shadow-md sm:size-28" src="{{ $gameService->background_image ?: $game->image }}" alt="{{ $gameService->name }}" width="112" height="112">
                @else
                    <span class="grid size-20 shrink-0 place-items-center rounded-[10px] bg-emerald-600 text-2xl font-extrabold text-white shadow-md sm:size-28">
                        {{ mb_substr($gameService->name, 0, 2) }}
                    </span>
                @endif
                <div class="min-w-0">
                    <p class="text-xs font-extrabold uppercase tracking-[0.14em] text-emerald-700">NapCarot · Dịch vụ {{ $game->name }}</p>
                    <h1 class="mt-2 break-words text-2xl font-extrabold tracking-tight text-slate-950 sm:text-4xl">{{ $gameService->name }}</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Chọn gói dịch vụ phù hợp và gửi yêu cầu của bạn.</p>
                </div>
            </div>
        </header>

        <section class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.82fr)] lg:gap-7" aria-label="Thông tin và đặt dịch vụ">
            <article class="min-w-0 rounded-[10px] border-2 border-slate-300 bg-white p-5 shadow-sm sm:p-7">
                <div class="border-b border-slate-200 pb-4">
                    <p class="text-sm font-bold text-emerald-700">Thông tin dịch vụ</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Mô tả về dịch vụ</h2>
                </div>
                @if (filled($gameService->description))
                    <div class="mt-4 whitespace-pre-line break-words text-sm leading-7 text-slate-600 sm:text-base">{{ $gameService->description }}</div>
                @else
                    <p class="mt-4 text-sm leading-7 text-slate-500">Thông tin chi tiết về dịch vụ đang được cập nhật.</p>
                @endif

            </article>

            <aside class="overflow-hidden rounded-[10px] border-2 border-emerald-300 bg-white shadow-md lg:sticky lg:top-24" data-game-service-order-panel>
                <div class="border-b border-emerald-200 bg-emerald-50 px-5 py-4 sm:px-6">
                    <p class="text-sm font-bold text-emerald-700">Đặt dịch vụ</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Tạo đơn mới</h2>
                </div>

                @if ($gameService->packages->isEmpty() || $gameService->servers->isEmpty())
                    <div class="m-5 rounded-[10px] border-2 border-dashed border-slate-300 bg-slate-50 p-5 text-sm font-semibold text-slate-500">
                        Dịch vụ chưa đủ gói hoặc máy chủ đang hoạt động để nhận đơn.
                    </div>
                @else
                    <form class="grid gap-5 p-5 sm:p-6" method="POST" action="{{ route('game-services.orders.store', ['game' => $game, 'gameService' => $gameService]) }}" data-game-service-order-form>
                        @csrf
                        <section class="grid gap-4" data-game-service-step-panel="1">
                            <div class="flex items-center gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-[5px] bg-emerald-600 text-sm font-extrabold text-white">1</span>
                                <div>
                                    <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-emerald-700">Bước 1</p>
                                    <h3 class="text-base font-extrabold text-slate-950">Chọn gói dịch vụ</h3>
                                </div>
                            </div>
                            <fieldset class="grid gap-2" data-game-service-package-list>
                                <legend class="mb-1 text-sm font-bold text-slate-700">Gói dịch vụ</legend>
                                @foreach ($gameService->packages as $package)
                                    @php
                                        $price = $package->prices->first();
                                    @endphp
                                    <label class="relative cursor-pointer">
                                        <input
                                            class="peer sr-only"
                                            type="radio"
                                            name="package_id"
                                            value="{{ $package->id }}"
                                            data-game-service-package-option
                                            data-price="{{ $price->price }}"
                                            data-quantity-enabled="{{ $price->quantity_enabled ? '1' : '0' }}"
                                            data-min-quantity="{{ $price->quantity_enabled ? $price->min_quantity : 1 }}"
                                            data-max-quantity="{{ $price->quantity_enabled ? $price->max_quantity : 1 }}"
                                            @checked($selectedPackageId === $package->id)
                                            required
                                        >
                                        <span class="absolute right-3.5 top-1/2 z-10 grid size-6 -translate-y-1/2 place-items-center rounded-[5px] border-2 border-slate-300 bg-white text-white transition peer-checked:border-emerald-600 peer-checked:bg-emerald-600" aria-hidden="true">
                                            <i class="bx bx-check text-lg"></i>
                                        </span>
                                        <span class="flex min-h-14 items-center rounded-[5px] border-2 border-slate-200 bg-white px-3.5 py-3 pr-12 transition hover:border-emerald-300 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500 peer-focus-visible:ring-offset-2">
                                            <span class="min-w-0">
                                                <span class="block truncate text-sm font-bold text-slate-900">{{ $package->name }}</span>
                                                <span class="mt-0.5 block text-sm font-extrabold text-amber-700">{{ number_format($price->price, 0, ',', '.') }}đ</span>
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700" data-game-service-quantity-field>
                                Số lượng
                                <input name="quantity" type="number" required value="{{ old('quantity', 1) }}" min="1" max="1" class="min-h-11 rounded-[5px] border-2 border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500" data-game-service-quantity>
                                <small class="font-normal text-slate-500" data-game-service-quantity-help></small>
                            </label>
                        </section>

                        <hr class="border-0 border-t border-slate-200">

                        <section class="grid gap-4" data-game-service-step-panel="2">
                            <div class="flex items-center gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-[5px] bg-emerald-600 text-sm font-extrabold text-white">2</span>
                                <div>
                                    <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-emerald-700">Bước 2</p>
                                    <h3 class="text-base font-extrabold text-slate-950">Chọn máy chủ</h3>
                                </div>
                            </div>
                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <select name="server_id" required class="min-h-11 rounded-[5px] border-2 border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <option value="">Chọn máy chủ</option>
                                    @foreach ($gameService->servers as $server)
                                        <option value="{{ $server->id }}" @selected((int) old('server_id') === $server->id)>{{ $server->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </section>

                        <hr class="border-0 border-t border-slate-200">

                        <section class="grid gap-4" data-game-service-step-panel="3">
                            <div class="flex items-center gap-3">
                                <span class="grid size-8 shrink-0 place-items-center rounded-[5px] bg-emerald-600 text-sm font-extrabold text-white">3</span>
                                <div>
                                    <p class="text-xs font-extrabold uppercase tracking-[0.12em] text-emerald-700">Bước 3</p>
                                    <h3 class="text-base font-extrabold text-slate-950">Nhập dữ liệu yêu cầu</h3>
                                </div>
                            </div>
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
                                            <select name="payload[{{ $fieldKey }}]" @required($fieldRequired) class="min-h-11 rounded-[5px] border-2 border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                                <option value="">{{ $field['placeholder'] ?? 'Chọn thông tin' }}</option>
                                                @foreach ($field['options'] ?? [] as $option)
                                                    <option value="{{ $option['value'] }}" @selected(old('payload.'.$fieldKey) === (string) $option['value'])>{{ $option['text'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input name="payload[{{ $fieldKey }}]" type="{{ in_array($fieldType, ['number', 'password'], true) ? $fieldType : 'text' }}" @required($fieldRequired) @if ($fieldType !== 'password') value="{{ old('payload.'.$fieldKey) }}" @endif @if ($fieldType === 'number' && is_numeric($field['min'] ?? null)) min="{{ $field['min'] }}" @endif @if ($fieldType === 'number' && is_numeric($field['max'] ?? null)) max="{{ $field['max'] }}" @endif @if ($fieldType === 'number' && is_numeric($field['step'] ?? null)) step="{{ $field['step'] }}" @endif placeholder="{{ $field['placeholder'] ?? '' }}" class="min-h-11 rounded-[5px] border-2 border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500 px-2">
                                        @endif
                                    </label>
                                @endif
                            @endforeach

                            <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                <span>Ghi chú <small class="font-normal text-slate-400">(không bắt buộc)</small></span>
                                <textarea name="note" rows="4" maxlength="1000" class="min-h-24 resize-y rounded-[5px] border-2 border-slate-300 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Nhập thông điệp hoặc yêu cầu thêm cho cộng tác viên...">{{ old('note') }}</textarea>
                            </label>

                            @guest
                                <label class="grid gap-1.5 text-sm font-bold text-slate-700">
                                    Email nhận thông tin đơn
                                    <input name="email" type="email" required value="{{ old('email') }}" autocomplete="email" class="min-h-11 rounded-[5px] border-2 border-slate-300 bg-white text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="email@example.com">
                                </label>
                            @endguest

                            <div class="flex items-center justify-between gap-4 rounded-[10px] border-2 border-amber-200 bg-amber-50 px-4 py-3">
                                <span class="text-sm font-bold text-amber-900">Tổng thanh toán</span>
                                <strong class="text-xl text-amber-700" data-game-service-total>0đ</strong>
                            </div>
                        </section>
                        <button type="submit" class="client-button min-h-12 w-full justify-center">Đặt đơn dịch vụ</button>
                    </form>
                @endif
            </aside>
        </section>

        <section class="overflow-hidden rounded-[10px] border-2 border-slate-300 bg-white shadow-sm" aria-labelledby="recent-game-service-orders">
            <header class="border-b-2 border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                <p class="text-sm font-bold text-emerald-700">Hoạt động gần đây</p>
                <h2 id="recent-game-service-orders" class="mt-1 text-2xl font-extrabold text-slate-950">5 đơn mới nhất</h2>
            </header>
            <div>
                @forelse ($recentOrders as $order)
                    <div class="grid gap-2 border-b border-slate-200 px-5 py-4 last:border-b-0 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-5 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate font-bold text-slate-900">{{ $order->package_name }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">{{ $order->server_name }} · Số lượng {{ $order->quantity }}</p>
                        </div>
                        <time class="text-xs font-semibold text-slate-500" datetime="{{ $order->created_at?->toISOString() }}">{{ $order->created_at?->diffForHumans() }}</time>
                        <x-client.game-service-order-status :status="$order->status" />
                    </div>
                @empty
                    <p class="p-6 text-center text-sm font-semibold text-slate-500">Chưa có đơn dịch vụ nào.</p>
                @endforelse
            </div>
        </section>

        @if (filled($seoContentHtml->toHtml()))
            <article class="min-w-0 border-y-2 border-slate-300 py-7 sm:py-9" aria-labelledby="game-service-seo-title">
                <h2 id="game-service-seo-title" class="border-l-4 border-emerald-500 pl-4 text-2xl font-extrabold text-slate-950">Thông tin chi tiết {{ $gameService->name }}</h2>
                <div class="article-content mt-5 min-w-0 max-w-full break-words" data-client-image-viewer>{!! $seoContentHtml !!}</div>
            </article>
        @endif

        @if ($seoFaqs->isNotEmpty())
            <section class="rounded-[10px] border-2 border-slate-300 bg-slate-50 p-5 shadow-sm sm:p-6" aria-labelledby="game-service-faq-title">
                <div class="border-b-2 border-slate-200 pb-4">
                    <p class="text-sm font-bold text-emerald-700">Giải đáp nhanh</p>
                    <h2 id="game-service-faq-title" class="mt-1 text-2xl font-extrabold text-slate-950">Câu hỏi thường gặp</h2>
                </div>
                <div class="mt-5 grid gap-3">
                    @foreach ($seoFaqs as $faq)
                        <details class="group rounded-[10px] border-2 border-slate-200 bg-white px-5 py-4 open:border-emerald-300 open:shadow-sm">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-bold text-slate-900">
                                <span>{{ $faq['question'] }}</span>
                                <i class="bx bx-chevron-down text-xl text-slate-400 transition group-open:rotate-180" aria-hidden="true"></i>
                            </summary>
                            <p class="mt-3 border-t-2 border-slate-100 pt-3 text-sm leading-7 text-slate-600">{{ $faq['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
