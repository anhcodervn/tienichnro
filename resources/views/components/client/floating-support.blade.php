@props(['channels' => [], 'raised' => false])

@php
    $channels = collect($channels)
        ->filter(fn (mixed $channel): bool => is_array($channel) && isset($channel['icon'], $channel['url']))
        ->values();
@endphp

@if ($channels->isNotEmpty())
    <div
        @class([
            'fixed right-4 z-[60] flex flex-col items-center gap-3 sm:right-6',
            'bottom-[calc(env(safe-area-inset-bottom)+6rem)]' => $raised,
            'bottom-[calc(env(safe-area-inset-bottom)+1rem)]' => ! $raised,
        ])
        data-floating-support
    >
        <div id="floating-support-channels" class="hidden flex-col items-center gap-3" data-floating-support-list>
            @foreach ($channels as $channel)
                <a
                    href="{{ $channel['url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="grid h-14 w-14 place-items-center overflow-hidden rounded-full border border-white/80 bg-white p-2 shadow-[0_10px_28px_rgba(15,23,42,0.22)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_14px_32px_rgba(15,23,42,0.28)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fuchsia-600 focus-visible:ring-offset-2 sm:h-16 sm:w-16"
                    aria-label="Mở kênh hỗ trợ"
                    data-floating-support-link
                >
                    <img src="{{ $channel['icon'] }}" alt="" class="h-full w-full object-contain" loading="lazy">
                </a>
            @endforeach
        </div>

        <button
            type="button"
            class="group grid h-16 w-16 place-items-center rounded-full bg-fuchsia-600 text-white shadow-[0_12px_32px_rgba(192,38,211,0.38)] transition duration-200 hover:-translate-y-0.5 hover:bg-fuchsia-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-fuchsia-600 focus-visible:ring-offset-2 sm:h-[4.5rem] sm:w-[4.5rem]"
            aria-label="Mở danh sách hỗ trợ"
            aria-controls="floating-support-channels"
            aria-expanded="false"
            data-floating-support-toggle
        >
            <i class="bx bx-headphone text-3xl group-aria-expanded:hidden" aria-hidden="true"></i>
            <i class="bx bx-x hidden text-4xl group-aria-expanded:block" aria-hidden="true"></i>
        </button>
    </div>
@endif
