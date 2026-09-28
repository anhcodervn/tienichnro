@props([
    'title',
    'content',
    'displayMode' => 'modal',
    'allowDismiss' => false,
    'dismissHours' => 24,
    'popupKey',
])

@php
    $isModal = $displayMode === 'modal';
@endphp

<div
    @class([
        'fixed inset-0 z-[80]',
        'grid place-items-center p-3 sm:p-6' => $isModal,
        'pointer-events-none flex items-end justify-end p-3 sm:p-6' => ! $isModal,
    ])
    data-home-popup
    data-popup-key="{{ $popupKey }}"
    data-dismiss-enabled="{{ $allowDismiss ? 'true' : 'false' }}"
    data-dismiss-hours="{{ $dismissHours }}"
    data-display-mode="{{ $displayMode }}"
    aria-hidden="true"
    hidden
>
    @if ($isModal)
        <button class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" type="button" tabindex="-1" data-home-popup-close aria-label="Đóng thông báo"></button>
    @endif

    <section
        @class([
            'relative flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-[12px] border border-slate-200 bg-white shadow-2xl outline-none sm:max-h-[calc(100dvh-3rem)]',
            'max-w-2xl' => $isModal,
            'pointer-events-auto max-w-md' => ! $isModal,
        ])
        role="dialog"
        @if ($isModal) aria-modal="true" @endif
        aria-labelledby="home-popup-title"
        tabindex="-1"
        data-home-popup-panel
    >
        <header class="flex items-start justify-between gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:px-5 sm:py-4">
            <div class="flex min-w-0 items-start gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-xl text-emerald-700" aria-hidden="true">
                    <i class="bx bx-bell"></i>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Thông báo</p>
                    <h2 id="home-popup-title" class="mt-0.5 text-lg font-extrabold leading-6 text-slate-950">{{ $title }}</h2>
                </div>
            </div>
            <button class="grid h-9 w-9 shrink-0 place-items-center rounded-full border border-slate-200 bg-white text-xl text-slate-500 transition hover:border-slate-300 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" type="button" data-home-popup-close aria-label="Đóng thông báo">
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="article-content home-popup-content min-h-0 overflow-y-auto px-4 py-4 text-sm leading-7 text-slate-700 sm:px-5" data-client-image-viewer>
            {!! $content->toHtml() !!}
        </div>

        <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:px-5">
            <p class="text-xs leading-5 text-slate-500">
                @if ($allowDismiss)
                    Sau khi đóng, thông báo sẽ tạm ẩn trong {{ number_format((int) $dismissHours, 0, ',', '.') }} giờ.
                @else
                    Thông báo sẽ hiển thị lại khi tải lại trang chủ.
                @endif
            </p>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button class="client-button-secondary min-h-10 justify-center bg-white px-5" type="button" data-home-popup-close>
                    Đã hiểu
                </button>
                @if ($allowDismiss)
                    <button class="client-button min-h-10 justify-center px-5" type="button" data-home-popup-dismiss>
                        Đóng trong {{ number_format((int) $dismissHours, 0, ',', '.') }} giờ
                    </button>
                @endif
            </div>
        </footer>
    </section>
</div>
