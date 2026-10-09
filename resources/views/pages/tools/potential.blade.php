@extends('client.layouts.app')
@section('title', 'Tính tiềm năng Ngọc Rồng Online')
@section('description', 'Tính tổng tiềm năng đã nâng HP, KI, sức đánh, giáp và chí mạng theo hành tinh trong Ngọc Rồng Online.')
@section('canonical', route('tools.potential'))
@section('content')
<section class="client-container py-8 sm:py-12">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <p class="text-sm font-bold text-emerald-700">Công cụ Ngọc Rồng Online</p>
            <h1 class="mt-2 text-3xl font-extrabold text-slate-900">Tính tiềm năng</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Nhập chỉ số gốc của nhân vật để kiểm tra tiềm năng đã nâng và cấp độ tương ứng. Dùng chỉ số chưa cộng trang bị, ngọc hoặc hiệu ứng hỗ trợ.</p>
        </div>
        @if(!$service['is_enabled'])
            <x-client.service-maintenance :message="$service['maintenance_message']" />
        @else
        <form action="{{ route('tools.potential.calculate') }}" method="POST" data-potential-form class="client-card grid gap-4 p-4 sm:p-6">
            @csrf
            <label class="client-label">Hành tinh
                <select class="client-input mt-2" name="planet" required>
                    <option value="">Chọn hành tinh</option>
                    @foreach($planets as $value => $planet)
                        <option value="{{ $value }}" data-hp="{{ $planet['hp'] }}" data-ki="{{ $planet['ki'] }}" data-attack="{{ $planet['attack'] }}">{{ $planet['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="grid grid-cols-2 gap-4">
                @foreach(['hp' => ['HP gốc', 100, 1000000000], 'ki' => ['KI gốc', 100, 1000000000], 'attack' => ['Sức đánh gốc', 12, 1000000], 'armor' => ['Giáp gốc', 0, 1000000], 'critical' => ['Chí mạng (%)', 0, 100]] as $field => [$label, $minimum, $maximum])
                <label class="client-label">{{ $label }}
                    <input class="client-input mt-2" name="{{ $field }}" type="number" min="{{ $minimum }}" max="{{ $maximum }}" step="1" inputmode="numeric" placeholder="{{ $field === 'critical' ? 'Ví dụ: 5' : 'Nhập chỉ số' }}" required>
                </label>
                @endforeach
            </div>
            <p class="text-xs leading-5 text-slate-600" data-potential-base>Chọn hành tinh để xem chỉ số gốc tối thiểu.</p>
            <button type="submit" class="client-button w-full">Kiểm tra</button>
            <noscript><p class="text-sm text-rose-700">Vui lòng bật JavaScript để kiểm tra và xem kết quả.</p></noscript>
        </form>
        @endif
    </div>
</section>
@endsection
