@extends('client.layouts.app')
@section('title', 'Tính tiềm năng Ngọc Rồng Online')
@section('description', 'Tính tổng tiềm năng đã nâng HP, KI, sức đánh, giáp và chí mạng theo hành tinh trong Ngọc Rồng Online.')
@section('canonical', route('tools.potential'))
@section('tool_title', 'Tính tiềm năng')
@section('tool_description', 'Nhập chỉ số gốc của nhân vật để kiểm tra tiềm năng đã nâng và cấp độ tương ứng. Dùng chỉ số chưa cộng trang bị, ngọc hoặc hiệu ứng hỗ trợ.')
@section('content')
        @if(!$service['is_enabled'])
            <x-client.service-maintenance :message="$service['maintenance_message']" />
        @else
        <div class="grid w-full min-w-0 items-start gap-5 lg:grid-cols-2" data-potential-workspace>
        <form action="{{ route('tools.potential.calculate') }}" method="POST" data-potential-form class="client-card grid gap-4 p-4 sm:p-6" aria-labelledby="potential-input-title">
            @csrf
            <header class="border-b border-slate-100 pb-4">
                <h2 id="potential-input-title" class="text-lg font-bold text-slate-900">Chỉ số nhân vật</h2>
                <p class="mt-1 text-sm text-slate-500">Chọn hành tinh và nhập chỉ số gốc cần kiểm tra.</p>
            </header>
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
            <p data-potential-error role="alert" class="whitespace-pre-line rounded-[10px] bg-rose-50 p-3 text-sm leading-6 text-rose-700" hidden></p>
            <button type="submit" class="client-button w-full">Kiểm tra</button>
            <noscript><p class="text-sm text-rose-700">Vui lòng bật JavaScript để kiểm tra và xem kết quả.</p></noscript>
        </form>
        <section class="client-card overflow-hidden" aria-labelledby="potential-result-title">
            <header class="border-b border-slate-100 bg-emerald-50 p-4 sm:p-6">
                <h2 id="potential-result-title" class="text-lg font-bold text-emerald-900">Kết quả tính tiềm năng</h2>
                <p class="mt-1 text-sm text-emerald-800">Tổng tiềm năng đã nâng theo chỉ số nhân vật.</p>
            </header>
            <p class="p-5 text-sm leading-6 text-slate-500 sm:p-6" data-potential-empty role="status">Nhập chỉ số rồi bấm Kiểm tra để xem kết quả tại đây.</p>
            <div class="p-4 sm:p-6" data-potential-result role="status" aria-live="polite" aria-atomic="true" hidden>
                <p class="text-sm text-slate-500">Tổng tiềm năng đã nâng</p>
                <p class="mt-2 break-words text-3xl font-extrabold tabular-nums text-emerald-700" data-potential-total></p>
                <dl class="mt-5 grid gap-3 text-sm">
                    <div class="flex flex-wrap justify-between gap-2"><dt class="text-slate-500">Hành tinh</dt><dd class="font-semibold text-slate-900" data-potential-planet></dd></div>
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-4"><dt class="text-slate-500">Cấp độ</dt><dd class="font-semibold text-emerald-700" data-potential-level></dd></div>
                    @foreach(['hp' => 'HP', 'ki' => 'KI', 'attack' => 'Sức đánh', 'armor' => 'Giáp', 'critical' => 'Chí mạng'] as $field => $label)
                        <div class="flex flex-wrap justify-between gap-2"><dt class="text-slate-600">{{ $label }}</dt><dd class="font-semibold tabular-nums text-slate-900"><span data-potential-cost="{{ $field }}"></span> <span class="font-normal text-slate-500">tiềm năng</span></dd></div>
                    @endforeach
                </dl>
            </div>
        </section>
        </div>
        @endif
@endsection
