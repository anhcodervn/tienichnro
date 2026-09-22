@props(['games'])

<div
    id="game-picker-modal"
    class="fixed inset-0 z-[80]"
    data-game-picker-modal
    aria-hidden="true"
    hidden
>
    <button
        type="button"
        class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]"
        data-game-picker-close
        data-game-picker-backdrop
        tabindex="-1"
        aria-label="Đóng danh sách game"
    ></button>

    <section
        class="absolute inset-x-3 top-1/2 mx-auto flex max-h-[min(42rem,calc(100dvh-2rem))] max-w-4xl -translate-y-1/2 flex-col overflow-hidden rounded-[10px] border border-slate-200 bg-white shadow-2xl sm:inset-x-6"
        data-game-picker-panel
        role="dialog"
        aria-modal="true"
        aria-labelledby="game-picker-title"
        aria-describedby="game-picker-description"
        tabindex="-1"
    >
        <header class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
            <div class="min-w-0">
                <h2 id="game-picker-title" class="text-lg font-extrabold text-slate-950">Chọn game muốn nạp</h2>
                <p id="game-picker-description" class="mt-1 text-sm leading-5 text-slate-500">Chạm vào game để mở đúng trang nạp.</p>
            </div>
            <button
                type="button"
                class="grid h-11 w-11 shrink-0 place-items-center rounded-[5px] border border-slate-300 bg-white text-xl text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600"
                data-game-picker-close
                aria-label="Đóng danh sách game"
            >
                <i class="bx bx-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="min-h-0 overflow-y-auto overscroll-contain p-3 sm:p-5">
            <div class="grid grid-cols-3 gap-x-3 gap-y-5 min-[420px]:grid-cols-4 sm:grid-cols-5 md:grid-cols-6" data-game-picker-list>
                @forelse ($games as $game)
                    <a
                        href="{{ route('topup.game', ['game' => $game]) }}"
                        class="group flex min-w-0 flex-col items-center gap-2 rounded-[10px] text-center transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-4"
                        data-game-picker-link
                    >
                        <span class="grid aspect-square w-full max-w-[7rem] place-items-center overflow-hidden rounded-[12px] border border-slate-200 bg-slate-100 text-xl font-extrabold text-slate-600 shadow-sm transition group-hover:shadow-md">
                            @if (filled($game->image))
                                <img class="h-full w-full object-cover" src="{{ $game->image }}" alt="Icon {{ $game->name }}" loading="lazy" width="112" height="112" decoding="async">
                            @else
                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($game->short_name ?: $game->name, 0, 1)) }}
                            @endif
                        </span>
                        <span class="line-clamp-2 min-h-10 w-full text-sm font-semibold leading-5 text-slate-900 group-hover:text-emerald-700">{{ $game->name }}</span>
                    </a>
                @empty
                    <div class="col-span-full rounded-[8px] border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center">
                        <i class="bx bx-game mb-2 text-3xl text-slate-400" aria-hidden="true"></i>
                        <p class="text-sm font-semibold text-slate-600">Chưa có game đang mở nạp.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>
</div>
