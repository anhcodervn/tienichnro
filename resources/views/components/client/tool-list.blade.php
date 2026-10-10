@props(['tools'])

<div {{ $attributes->class(['grid w-full grid-cols-2 gap-x-3 gap-y-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6']) }}>
            @foreach($tools as $tool)
                @php
                    $toolLink = ($tool['is_enabled'] ?? true) && \App\Support\SafeNavigationUrl::passes($tool['url'] ?? null)
                        ? $tool['url']
                        : (filled($tool['route'] ?? null) && \Illuminate\Support\Facades\Route::has($tool['route']) ? route($tool['route'], $tool['parameters'] ?? []) : null);
                @endphp
                @if($toolLink)
                <a class="group flex min-w-0 flex-col items-center gap-2 rounded-[10px] text-center transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-4" href="{{ $toolLink }}" title="{{ $tool['description'] }}">
                    <span class="relative grid aspect-square w-full max-w-[7.5rem] place-items-center rounded-[12px] border border-emerald-200 bg-gradient-to-br from-emerald-50 to-cyan-50 text-4xl text-emerald-700 shadow-sm transition group-hover:shadow-md">
                        @if(($tool['icon_type'] ?? 'icon') === 'image' && filled($tool['image_url'] ?? null))
                            <img src="{{ $tool['image_url'] }}" alt="" width="256" height="256" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full rounded-[12px] object-cover" />
                        @else
                            <i class="bx {{ $tool['icon'] }}" aria-hidden="true"></i>
                        @endif
                        @if(!($tool['is_enabled'] ?? true))<span class="absolute inset-x-1 bottom-2 mx-auto w-fit max-w-full rounded-[6px] border border-rose-200 bg-rose-100 px-1.5 py-0.5 text-[10px] font-semibold leading-4 text-rose-800 sm:text-xs">Bảo trì</span>@endif
                    </span>
                    <strong class="line-clamp-2 min-h-10 w-full text-sm font-semibold leading-5 text-slate-900 group-hover:text-emerald-700">{{ $tool['name'] }}</strong>
                </a>
                @else
                <button type="button" class="flex min-w-0 cursor-not-allowed flex-col items-center gap-2 rounded-[10px] text-center" disabled title="{{ $tool['description'] }}">
                    <span class="relative grid aspect-square w-full max-w-[7.5rem] place-items-center rounded-[12px] border border-slate-200 bg-slate-50 text-4xl text-slate-400">
                        @if(($tool['icon_type'] ?? 'icon') === 'image' && filled($tool['image_url'] ?? null))
                            <img src="{{ $tool['image_url'] }}" alt="" width="256" height="256" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full rounded-[12px] object-cover" />
                        @else
                            <i class="bx {{ $tool['icon'] }}" aria-hidden="true"></i>
                        @endif
                        <span class="absolute inset-x-1 bottom-2 mx-auto w-fit max-w-full rounded-[6px] border border-amber-200 bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold leading-4 text-amber-900 sm:text-xs">{{ ($tool['is_enabled'] ?? true) ? 'Sắp ra mắt' : 'Bảo trì' }}</span>
                    </span>
                    <strong class="line-clamp-2 min-h-10 w-full text-sm font-semibold leading-5 text-slate-600">{{ $tool['name'] }}</strong>
                </button>
                @endif
            @endforeach
</div>
