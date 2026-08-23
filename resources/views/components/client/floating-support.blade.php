@props(['raised' => false])

<a
    @class([
        'group fixed right-3 z-30 inline-flex min-h-14 items-center gap-2.5 rounded-full border border-emerald-400/70 bg-emerald-600 p-2 pr-3 text-white shadow-[0_12px_32px_rgba(5,150,105,0.35)] transition duration-200 hover:-translate-y-1 hover:bg-emerald-700 hover:shadow-[0_16px_36px_rgba(5,150,105,0.42)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 sm:bottom-6 sm:right-6 sm:pr-4',
        'bottom-[calc(env(safe-area-inset-bottom)+6rem)]' => $raised,
        'bottom-[calc(env(safe-area-inset-bottom)+1rem)]' => ! $raised,
    ])
    href="{{ route('content.contact') }}"
    aria-label="Liên hệ hỗ trợ"
    title="Liên hệ hỗ trợ"
    data-floating-support
>
    <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-xl text-emerald-700 shadow-sm">
        <span class="absolute inset-0 rounded-full bg-white/70 motion-safe:animate-ping motion-reduce:hidden" aria-hidden="true"></span>
        <i class="bx bx-message-circle-dots relative" aria-hidden="true"></i>
    </span>
    <span class="hidden min-[390px]:block">
        <strong class="block text-sm leading-5">Liên hệ hỗ trợ</strong>
        <span class="block text-[11px] font-medium text-emerald-100">Phản hồi nhanh</span>
    </span>
</a>
