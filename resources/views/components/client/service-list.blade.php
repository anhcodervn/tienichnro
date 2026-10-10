@props(['services'])

<div {{ $attributes->class(['grid w-full grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4']) }}>
    @foreach($services as $service)
        @php($serviceLink = $service['is_enabled'] && \App\Support\SafeNavigationUrl::passes($service['url']) ? $service['url'] : null)
        @if($serviceLink)
            <a href="{{ $serviceLink }}" class="group flex min-w-0 flex-col items-center gap-2 rounded-[10px] text-center transition hover:-translate-y-0.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-4" title="{{ $service['description'] }}">
        @else
            <button type="button" disabled class="flex min-w-0 cursor-not-allowed flex-col items-center gap-2 rounded-[10px] text-center opacity-60" title="{{ $service['description'] }}">
        @endif
            <span class="relative grid aspect-square w-full max-w-[7.5rem] place-items-center overflow-hidden rounded-[12px] border border-emerald-200 bg-gradient-to-br from-emerald-50 to-cyan-50 text-4xl text-emerald-700 shadow-sm">
                @if($service['icon_type'] === 'image' && filled($service['image_url']))
                    <img src="{{ $service['image_url'] }}" alt="" width="256" height="256" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover" />
                @else
                    <i class="bx {{ $service['icon'] }}" aria-hidden="true"></i>
                @endif
                @if(!$serviceLink)<span class="absolute inset-x-1 bottom-2 mx-auto w-fit rounded-[6px] border border-amber-200 bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-900">{{ $service['is_enabled'] ? 'Sắp ra mắt' : 'Bảo trì' }}</span>@endif
            </span>
            <strong class="line-clamp-2 min-h-10 w-full text-sm font-semibold leading-5 text-slate-900 group-hover:text-emerald-700">{{ $service['name'] }}</strong>
        @if($serviceLink)</a>@else</button>@endif
    @endforeach
</div>
