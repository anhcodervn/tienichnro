<fieldset @class([
    'home-package-fieldset',
    'w-full rounded-[8px] border border-slate-200 bg-white p-4 shadow-sm sm:p-5' => $stepLayout,
]) data-topup-step="{{ $stepLayout ? '2' : null }}" @if ($stepLayout) data-topup-step-card @endif>
    @if ($stepLayout)
        <legend class="sr-only">Bước 2: Chọn mệnh giá</legend>
        <header class="flex items-center gap-3">
            <b class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-cyan-700 text-sm text-white">2</b>
            <div>
                <h2 class="text-base font-extrabold text-slate-950">Bước 2: Chọn mệnh giá</h2>
                <p class="text-sm text-slate-500">Chọn gói nạp phù hợp.</p>
            </div>
        </header>
    @else
        <legend class="inline-flex items-center gap-1.5"><i class="bx bx-coins text-lg text-cyan-700" aria-hidden="true"></i>Chọn mệnh giá <span aria-hidden="true">*</span></legend>
    @endif

    @if ($topupMaintenanceEnabled ?? false)
        <div class="mt-4 rounded-[10px] border border-amber-200 bg-amber-50 px-4 py-5 text-center" data-topup-maintenance>
            <i class="bx bx-wrench mb-2 text-3xl text-amber-600" aria-hidden="true"></i>
            <p class="font-bold text-amber-900">Cổng nạp game đang bảo trì</p>
            <p class="mt-1 whitespace-pre-line text-sm leading-6 text-amber-800">{{ $topupMaintenanceMessage }}</p>
        </div>
    @else
    <label class="sr-only" for="topup-package">Gói nạp</label>
    <select id="topup-package" name="package_id" class="sr-only" aria-required="true" data-package-select>
        <option value="">Chọn gói nạp</option>
        @foreach ($games as $game)
            @foreach ($game->packages as $package)
                <option
                    value="{{ $package->id }}"
                    data-game="{{ $game->id }}"
                    data-name="{{ $package->name }}"
                    data-denomination="{{ (int) ($package->denomination ?? $package->original_price) }}"
                    data-original="{{ (int) $package->original_price }}"
                    data-retail="{{ (int) ($package->retail_price ?? $package->price) }}"
                    data-price="{{ (int) $package->price }}"
                    data-discount="{{ (float) $package->discount_percent }}"
                    data-min="{{ $game->min_quantity }}"
                    data-max="{{ $game->max_quantity }}"
                    data-reward-label="{{ $game->reward_label ?: 'Thực nhận' }}"
                    data-reward="{{ $package->carot_amount }}"
                    data-reward-x2="{{ $package->reward_x2_amount }}"
                    data-reward-x3="{{ $package->reward_x3_amount }}"
                    data-reward-first="{{ $package->first_topup_reward_amount }}"
                    data-rewards="{{ json_encode($package->rewardItems($game->reward_label), JSON_UNESCAPED_UNICODE) }}"
                    @selected((string) $requestedPackage === (string) $package->id)
                >{{ $package->name }} · {{ number_format((int) $package->price, 0, ',', '.') }}đ</option>
            @endforeach
        @endforeach
    </select>

    @foreach ($games as $game)
        <div class="home-package-grid" data-package-options="{{ $game->id }}" @if ((string) $initialGame !== (string) $game->id) hidden @endif>
            @forelse ($game->packages as $package)
                <button
                    type="button"
                    class="home-package-option"
                    data-package-button="{{ $package->id }}"
                    aria-pressed="{{ (string) $requestedPackage === (string) $package->id ? 'true' : 'false' }}"
                >
                    <span class="home-package-check" aria-hidden="true">✓</span>
                    @if ((float) $package->discount_percent > 0)
                        <span class="home-package-discount">-{{ number_format((float) $package->discount_percent, 0, ',', '.') }}%</span>
                    @endif
                    <strong class="home-package-name">{{ $package->name }}</strong>
                    <span class="home-package-original-price">
                        <span>Giá gốc</span>
                        <del>{{ number_format((int) ($package->original_price ?? $package->price), 0, ',', '.') }}đ</del>
                    </span>
                    <span class="home-package-payment-price">
                        <span>Thanh toán</span>
                        <span>{{ number_format((int) $package->price, 0, ',', '.') }}đ</span>
                    </span>
                </button>
            @empty
                <p class="home-package-empty">Bảng giá đang được cập nhật.</p>
            @endforelse
        </div>
    @endforeach
    @error('package_id')<p class="home-field-error">{{ $message }}</p>@enderror
    @endif
    @error('topup')<p class="home-field-error">{{ $message }}</p>@enderror
</fieldset>
