@extends('client.layouts.app')
@section('title', 'Dịch vụ')
@section('description', 'Danh sách dịch vụ và các gói sử dụng tại Tiện ích Ngọc Rồng Online.')
@section('content')
<section class="client-container py-8 sm:py-10">
    <h1 class="text-3xl font-extrabold text-slate-900">Dịch vụ</h1>
    <p class="mt-2 text-slate-600">Khám phá dịch vụ và các gói sử dụng phù hợp với nhu cầu của bạn.</p>
    <div class="mt-6 grid min-w-0 gap-5 md:grid-cols-2">
        @forelse($services as $service)
            <article class="client-card p-5" data-service-code="{{ $service['code'] }}">
                <h2 class="text-xl font-bold text-slate-900">{{ $service['name'] }}</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $service['description'] }}</p>
                @if(!$service['is_enabled'])
                    <p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $service['maintenance_message'] }}</p>
                @else
                    <div class="mt-4 grid gap-3">
                        @foreach($packages->get($service['code'], collect()) as $package)
                            <div class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 p-4" data-package-id="{{ $package->id }}">
                                <h3 class="break-words font-bold text-slate-900">{{ $package->name }}</h3>
                                <p class="mt-1 font-bold text-emerald-700">{{ number_format($package->price, 0, ',', '.') }} đ</p>
                                <p class="mt-1 text-sm text-slate-600">{{ match($package->billing_type) { 'usage' => number_format($package->usage_limit, 0, ',', '.').' lượt', 'time' => $package->duration_days.' ngày', default => 'Vĩnh viễn' } }}</p>
                                @if($package->description)<p class="mt-2 whitespace-pre-line break-words text-sm text-slate-600">{{ $package->description }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                    @if($service['public_url'])
                        <a class="client-button mt-4" href="{{ $service['public_url'] }}">Mở dịch vụ</a>
                    @else
                        <p class="mt-4 text-sm font-semibold text-slate-500">Sắp ra mắt</p>
                    @endif
                @endif
            </article>
        @empty
            <p class="text-slate-600">Chưa có dịch vụ được cấu hình.</p>
        @endforelse
    </div>
</section>
@endsection
