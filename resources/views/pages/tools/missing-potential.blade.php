@extends('client.layouts.app')
@section('title', 'Tính tiềm năng còn thiếu')
@section('description', 'Tính tiềm năng cần thêm để nâng HP, KI, sức đánh, giáp và chí mạng đến chỉ số mong muốn.')
@section('canonical', route('tools.missing-potential'))
@section('content')
    <section class="client-container py-8 sm:py-12">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <p class="text-sm font-bold text-emerald-700">Công cụ Ngọc Rồng Online</p>
                <span class="mt-2 block text-3xl font-extrabold text-slate-900">Tính tiềm năng còn thiếu</span>
                <p class="mt-3 text-sm leading-6 text-slate-600">Nhập chỉ số gốc hiện tại và chỉ số muốn đạt. Dùng chỉ số
                    chưa cộng trang bị, ngọc hoặc hiệu ứng hỗ trợ.</p>
            </div>
            @if (!$service['is_enabled'])
                <x-client.service-maintenance :message="$service['maintenance_message']" />
            @else
                <form action="{{ route('tools.missing-potential.calculate') }}" method="POST"
                    class="client-card grid gap-5 p-4 sm:p-6">
                    @csrf
                    @if ($errors->any())
                        <div role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">
                            <ul class="list-inside list-disc">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <label class="client-label">Hành tinh (dt thì bỏ qua)
                        <select class="client-input mt-2" name="planet" required>
                            <option value="">Chọn hành tinh</option>
                            @foreach ($planets as $value => $planet)
                                <option value="{{ $value }}" @selected(old('planet', $inputs['planet'] ?? '') === $value)>{{ $planet['label'] }} — HP
                                    {{ $planet['hp'] }}, KI {{ $planet['ki'] }}, sức đánh {{ $planet['attack'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-4">
                        <h2 class="text-sm font-bold text-slate-900">Chỉ số hiện tại</h2>
                        <h2 class="text-sm font-bold text-slate-900">Chỉ số mong muốn</h2>
                        @foreach (['hp' => ['HP gốc', 100, 1000000000], 'ki' => ['KI gốc', 100, 1000000000], 'attack' => ['Sức đánh gốc', 12, 1000000], 'armor' => ['Giáp gốc', 0, 1000000], 'critical' => ['Chí mạng (%)', 0, 100]] as $field => [$label, $minimum, $maximum])
                            @foreach (['current' => 'hiện tại', 'target' => 'mong muốn'] as $group => $groupLabel)
                                <label class="client-label min-w-0">{{ $label }} <span
                                        class="sr-only">{{ $groupLabel }}</span>
                                    <input class="client-input mt-2 w-full min-w-0"
                                        name="{{ $group }}[{{ $field }}]" type="number"
                                        min="{{ $minimum }}" max="{{ $maximum }}" step="1"
                                        inputmode="numeric"
                                        value="{{ old($group . '.' . $field, $inputs[$group][$field] ?? '') }}" required>
                                </label>
                            @endforeach
                        @endforeach
                    </div>
                    <p class="text-xs leading-5 text-slate-600">Chỉ số phải đạt mức gốc của hành tinh đã chọn. Chỉ số mong
                        muốn bằng hoặc thấp hơn hiện tại cần thêm 0 tiềm năng.</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="client-label">Tiềm năng đang có (không bắt buộc)
                            <input class="client-input mt-2" type="number" name="available_potential" min="0"
                                max="9007199254740991" step="1" inputmode="numeric" placeholder="0"
                                value="{{ old('available_potential', $inputs['available_potential'] ?? '') }}">
                        </label>
                        <label class="client-label">Sức mạnh hiện tại (không bắt buộc)
                            <input class="client-input mt-2" type="number" name="current_power" min="0"
                                max="9007199254740991" step="1" inputmode="numeric"
                                placeholder="Nhập để tính sức mạnh sau nâng"
                                value="{{ old('current_power', $inputs['current_power'] ?? '') }}">
                        </label>
                    </div>
                    <button type="submit" class="client-button w-full">Tính tiềm năng còn thiếu</button>
                </form>
                @if ($result !== null)
                    <section class="client-card mt-6 overflow-hidden" aria-labelledby="missing-potential-result">
                        <div class="bg-emerald-50 p-4 sm:p-6">
                            <h2 id="missing-potential-result" class="text-sm font-bold text-emerald-800">Tiềm năng còn phải
                                kiếm</h2>
                            <p class="mt-2 break-words text-2xl font-extrabold text-emerald-700 sm:text-3xl">
                                {{ number_format($result['remaining'], 0, ',', '.') }}</p>
                            <p class="mt-2 text-sm text-slate-600">Đã trừ tiềm năng đang có.
                                {{ $result['remaining'] == 0 ? 'Bạn đã đủ tiềm năng cho các chỉ số mong muốn.' : '' }}</p>
                        </div>
                        <dl class="grid gap-3 p-4 text-sm sm:p-6">
                            @foreach (['hp' => 'HP', 'ki' => 'KI', 'attack' => 'Sức đánh', 'armor' => 'Giáp', 'critical' => 'Chí mạng'] as $field => $label)
                                <div class="flex flex-wrap justify-between gap-2">
                                    <dt>{{ $label }}</dt>
                                    <dd class="font-semibold tabular-nums">
                                        {{ number_format($result['breakdown'][$field], 0, ',', '.') }}</dd>
                                </div>
                            @endforeach
                            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 pt-3 font-bold">
                                <dt>Tổng tiềm năng cần nâng</dt>
                                <dd>{{ number_format($result['total'], 0, ',', '.') }}</dd>
                            </div>
                            @if ($result['target_power'] !== null)
                                <div class="flex flex-wrap justify-between gap-2">
                                    <dt>Sức mạnh sau khi nâng (ước tính)</dt>
                                    <dd class="font-bold">{{ number_format($result['target_power'], 0, ',', '.') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>
                @endif
            @endif
        </div>
    </section>
@endsection
