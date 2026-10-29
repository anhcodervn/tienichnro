@extends('client.layouts.app')
@section('title', 'Tính chỉ số trang bị nâng cấp NRO')
@section('description', 'Tính chỉ số trang bị Ngọc Rồng Online từ +0 đến +7 theo chỉ số và cấp độ hiện tại.')
@section('canonical', route('tools.equipment-upgrade'))
@section('content')
<section class="client-container py-8 sm:py-12">
    <div class="mx-auto grid max-w-4xl gap-6">
        <header>
            <p class="text-sm font-bold text-emerald-700">Công cụ Ngọc Rồng Online</p>
            <span class="mt-2 block text-3xl font-extrabold text-slate-900">Tính chỉ số trang bị nâng cấp NRO</span>
            <p class="mt-3 text-sm leading-6 text-slate-600">Nhập chỉ số trên trang bị và cấp độ hiện tại để xem chỉ số tương ứng từ +0 đến +7.</p>
        </header>
        @if(!$service['is_enabled'])
            <x-client.service-maintenance :message="$service['maintenance_message']" />
        @else
            <form action="{{ route('tools.equipment-upgrade.calculate') }}" method="POST" class="client-card grid gap-5 p-4 sm:p-6">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="current-stat" class="client-label">Chỉ số hiện tại</label>
                        <input id="current-stat" class="client-input mt-2" name="current_stat" type="number" min="0" max="1000000000" step="any" inputmode="decimal" placeholder="Ví dụ: 2000" value="{{ old('current_stat', $inputs['current_stat'] ?? '') }}" aria-describedby="stat-help stat-error" @error('current_stat') aria-invalid="true" @enderror required>
                        <p id="stat-help" class="mt-2 text-xs text-slate-500">Nhập một chỉ số cần tính, như HP, KI, giáp hoặc sức đánh.</p>
                        @error('current_stat')<p id="stat-error" class="mt-2 text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="current-level" class="client-label">Cấp độ hiện tại</label>
                        <select id="current-level" name="current_level" class="client-input mt-2" aria-describedby="level-error" @error('current_level') aria-invalid="true" @enderror required>
                            <option value="">Chọn cấp độ trang bị</option>
                            @for($level = 0; $level <= 7; $level++)
                                <option value="{{ $level }}" @selected((string) old('current_level', $inputs['current_level'] ?? '') === (string) $level)>{{ $level === 0 ? '+0 (chưa nâng cấp)' : '+'.$level }}</option>
                            @endfor
                        </select>
                        @error('current_level')<p id="level-error" class="mt-2 text-sm text-rose-600" role="alert">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="client-button">Tính chỉ số</button>
                    <a href="{{ route('tools.equipment-upgrade') }}" class="client-button-secondary">Nhập lại</a>
                </div>
            </form>
            @if($results !== null)
                <section class="client-card overflow-hidden" aria-labelledby="equipment-results">
                    <div class="border-b border-slate-200 p-4 sm:p-6">
                        <h2 id="equipment-results" class="text-xl font-extrabold text-slate-900">Kết quả chỉ số trang bị</h2>
                        <p class="mt-2 text-sm text-slate-600">Cấp hiện tại được đánh dấu bên dưới. Cột làm tròn áp dụng quy tắc từ 0,5 trở lên làm tròn lên.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">Chỉ số trang bị từ cấp +0 đến +7</caption>
                            <thead class="bg-slate-50 text-slate-600"><tr><th scope="col" class="px-4 py-3">Cấp độ</th><th scope="col" class="px-4 py-3 text-right">Chỉ số</th><th scope="col" class="px-4 py-3 text-right">Làm tròn</th></tr></thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($results as $result)
                                    <tr @class(['bg-emerald-50 text-emerald-900' => $result['is_current'], 'text-slate-700' => !$result['is_current']])>
                                        <th scope="row" class="px-4 py-3 font-semibold">+{{ $result['level'] }} @if($result['is_current'])<span class="ml-2 text-xs text-emerald-700">Hiện tại</span>@endif</th>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format($result['value'], 10, ',', '.'), '0'), ',') }}</td>
                                        <td class="px-4 py-3 text-right font-bold tabular-nums">{{ number_format($result['rounded'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
            <aside class="client-card grid gap-3 p-4 text-sm leading-6 text-slate-600 sm:p-6">
                <h2 class="text-base font-bold text-slate-900">Chi tiết nâng cấp theo công thức tham khảo</h2>
                <p>Tỷ lệ thành công: +0 → +1: 80%; +1 → +2: 50%; +2 → +3: 20%; +3 → +4: 10%; +4 → +5: 5%; +5 → +6: 2%; +6 → +7: 1%.</p>
                <p>Thất bại khi nâng lên +3, +5, +7: tụt lần lượt về +1, +3, +5 và giảm 1% chỉ số. Các bước còn lại không tụt cấp và chỉ số.</p>
                <p>Kết quả tham khảo, chưa tính các lần nâng cấp thất bại.</p>
            </aside>
        @endif
    </div>
</section>
@endsection
