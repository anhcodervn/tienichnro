@php
    $selectedGame = $selectedGame ?? null;
    $walletBalance = $walletBalance ?? 0;
    $requestedGame = old('game_id', $selectedGame?->id ?? $games->first()?->id);
    $initialGame = $games->contains(fn ($game) => (string) $game->id === (string) $requestedGame)
        ? $requestedGame
        : $games->first()?->id;
    $initialGameModel = $games->first(fn ($game) => (string) $game->id === (string) $initialGame);
    $initialBulkPlaceholder = collect($initialGameModel?->checkoutFields() ?? [])
        ->map(fn ($field) => filled($field['placeholder'] ?? null) ? $field['placeholder'] : $field['label'])
        ->push('Số lượng thẻ')
        ->implode('|');
    $requestedPackage = old('package_id');
    $initialPackage = $games
        ->flatMap(fn ($game) => $game->packages)
        ->first(fn ($package) => (string) $package->id === (string) $requestedPackage);
    $initialPurchaseMode = old('purchase_mode', 'single') === 'bulk' ? 'bulk' : 'single';
    $initialBulkLines = collect(preg_split('/\R/u', trim((string) old('bulk_recipients', ''))) ?: [])
        ->filter(fn ($line) => trim($line) !== '')
        ->values();
    $initialBulkRecipientCount = $initialBulkLines->count();
    $initialBulkQuantity = $initialBulkLines->sum(function ($line): int {
        $values = explode('|', $line);
        $quantity = trim((string) end($values));

        return preg_match('/^[1-9]\d*$/D', $quantity) === 1 ? (int) $quantity : 0;
    });
    $initialSingleQuantity = max(1, (int) old('single_quantity', 1));
    $initialQuantity = $initialPurchaseMode === 'bulk' ? $initialBulkQuantity : $initialSingleQuantity;
    $initialOriginalTotal = (int) ($initialPackage?->original_price ?? 0) * $initialQuantity;
    $initialPaymentTotal = (int) ($initialPackage?->price ?? 0) * $initialQuantity;
    $initialDiscountTotal = max(0, $initialOriginalTotal - $initialPaymentTotal);
    $initialCanPayWithWallet = auth()->check()
        && $initialPackage
        && (int) $walletBalance >= $initialPaymentTotal;
    $initialPaymentMethod = $initialCanPayWithWallet && old('payment_method') !== 'bank_transfer'
        ? 'wallet'
        : 'bank_transfer';
    $initialCanSubmit = $initialPackage
        && $initialQuantity >= (int) $initialPackage->min_quantity
        && $initialQuantity <= (int) ($initialPackage->max_quantity ?: 100);
@endphp

<form method="POST" action="{{ route('checkout.store') }}" class="home-checkout-card" data-topup-form>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <input type="hidden" name="purchase_mode" value="{{ $initialPurchaseMode }}" data-purchase-mode>

    <header class="home-checkout-header">
        <div>
            <p class="home-checkout-eyebrow inline-flex items-center gap-1.5"><i class="bx bx-bolt text-base" aria-hidden="true"></i>Nạp game tự động</p>
            @if ($selectedGame)
                <h2>Nạp Carot <span data-selected-game-name>{{ $initialGameModel?->name ?? 'game Teamobi' }}</span></h2>
            @else
                <h1>Nạp Carot <span data-selected-game-name>{{ $initialGameModel?->name ?? 'game Teamobi' }}</span></h1>
            @endif
            @auth
                <p>Nhanh chóng · Rõ giá · Theo dõi trực tiếp trong lịch sử đơn hàng</p>
            @else
                <p>Nhanh chóng · Rõ giá · Theo dõi trạng thái qua email</p>
            @endauth
        </div>
        <span class="home-checkout-secure inline-flex items-center gap-1.5"><i class="bx bx-shield text-base" aria-hidden="true"></i>Giá được xác nhận lại trên hệ thống</span>
    </header>

    <div class="home-checkout-layout">
        <div class="home-checkout-fields">
            <div class="home-field">
                <label class="inline-flex items-center gap-1.5" for="topup-game"><i class="bx bx-joystick text-lg text-cyan-700" aria-hidden="true"></i>Game <span aria-hidden="true">*</span></label>
                <select id="topup-game" name="game_id" class="client-input" required>
                    <option value="">Chọn game</option>
                    @foreach ($games as $game)
                        <option value="{{ $game->id }}" @selected((string) $initialGame === (string) $game->id)>{{ $game->name }}</option>
                    @endforeach
                </select>
                @error('game_id')<p class="home-field-error">{{ $message }}</p>@enderror
            </div>

            <fieldset class="home-package-fieldset">
                <legend class="inline-flex items-center gap-1.5"><i class="bx bx-coins text-lg text-cyan-700" aria-hidden="true"></i>Chọn mệnh giá <span aria-hidden="true">*</span></legend>
                <label class="sr-only" for="topup-package">Gói nạp</label>
                <select id="topup-package" name="package_id" class="sr-only" aria-required="true" data-package-select>
                    <option value="">Chọn gói nạp</option>
                    @foreach ($games as $game)
                        @foreach ($game->packages as $package)
                            <option
                                value="{{ $package->id }}"
                                data-game="{{ $game->id }}"
                                data-server="{{ $package->game_server_id }}"
                                data-name="{{ $package->name }}"
                                data-denomination="{{ (int) ($package->denomination ?? $package->original_price) }}"
                                data-original="{{ (int) $package->original_price }}"
                                data-price="{{ (int) $package->price }}"
                                data-discount="{{ (float) $package->discount_percent }}"
                                data-min="{{ $package->min_quantity }}"
                                data-max="{{ $package->max_quantity ?: 100 }}"
                                data-reward-label="{{ $game->reward_label ?: 'Thực nhận' }}"
                                data-reward="{{ $package->carot_amount }}"
                                data-reward-x2="{{ $package->reward_x2_amount }}"
                                data-reward-x3="{{ $package->reward_x3_amount }}"
                                data-reward-first="{{ $package->first_topup_reward_amount }}"
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
                                data-package-server="{{ $package->game_server_id }}"
                                aria-pressed="{{ (string) $requestedPackage === (string) $package->id ? 'true' : 'false' }}"
                            >
                                <span class="home-package-check" aria-hidden="true">✓</span>
                                <strong>{{ $package->denomination ? number_format($package->denomination, 0, ',', '.') : $package->name }}</strong>
                                <span>{{ number_format((int) $package->price, 0, ',', '.') }}đ</span>
                                @if ((float) $package->discount_percent > 0)
                                    <small>-{{ number_format((float) $package->discount_percent, 0, ',', '.') }}%</small>
                                @endif
                            </button>
                        @empty
                            <p class="home-package-empty">Bảng giá đang được cập nhật.</p>
                        @endforelse
                    </div>
                @endforeach
                @error('package_id')<p class="home-field-error">{{ $message }}</p>@enderror
            </fieldset>

            <select name="server_id" data-server-source hidden>
                <option value="">Chọn máy chủ</option>
                @foreach ($games as $game)
                    @foreach ($game->servers as $server)
                        <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }}</option>
                    @endforeach
                @endforeach
            </select>

            <div class="home-purchase-tabs" role="tablist" aria-label="Hình thức nạp">
                <button
                    id="purchase-tab-single"
                    type="button"
                    role="tab"
                    data-purchase-tab="single"
                    aria-controls="purchase-panel-single"
                    aria-selected="{{ $initialPurchaseMode === 'single' ? 'true' : 'false' }}"
                    tabindex="{{ $initialPurchaseMode === 'single' ? '0' : '-1' }}"
                ><i class="bx bx-user text-lg" aria-hidden="true"></i><span>Nạp 1 tài khoản</span></button>
                <button
                    id="purchase-tab-bulk"
                    type="button"
                    role="tab"
                    data-purchase-tab="bulk"
                    aria-controls="purchase-panel-bulk"
                    aria-selected="{{ $initialPurchaseMode === 'bulk' ? 'true' : 'false' }}"
                    tabindex="{{ $initialPurchaseMode === 'bulk' ? '0' : '-1' }}"
                ><i class="bx bx-group text-lg" aria-hidden="true"></i><span>Nạp nhiều tài khoản</span></button>
            </div>
            @error('purchase_mode')<p class="home-field-error">{{ $message }}</p>@enderror

            <div id="purchase-panel-single" role="tabpanel" aria-labelledby="purchase-tab-single" data-purchase-panel="single" @if ($initialPurchaseMode !== 'single') hidden @endif>
                <div class="home-field">
                    <label class="inline-flex items-center gap-1.5" for="topup-server-single"><i class="bx bx-server text-lg text-cyan-700" aria-hidden="true"></i>Máy chủ <span aria-hidden="true">*</span></label>
                    <select id="topup-server-single" class="client-input" data-server-picker="single" @disabled($initialPurchaseMode !== 'single') @if ($initialPurchaseMode === 'single') required @endif>
                        <option value="">Chọn máy chủ</option>
                        @foreach ($games as $game)
                            @foreach ($game->servers as $server)
                                <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('server_id')<p class="home-field-error">{{ $message }}</p>@enderror
                </div>

                @foreach ($games as $game)
                    <div class="home-recipient-grid" data-recipient-fields="{{ $game->id }}" @if ((string) $initialGame !== (string) $game->id) hidden @endif>
                        @foreach ($game->checkoutFields() as $field)
                            <div class="home-field home-recipient-field" data-field-key="{{ $field['key'] }}">
                                <label for="recipient-{{ $game->id }}-{{ $field['key'] }}">
                                    {{ $field['label'] }} @if ($field['required'])<span aria-hidden="true">*</span>@endif
                                </label>
                                <input
                                    id="recipient-{{ $game->id }}-{{ $field['key'] }}"
                                    class="client-input"
                                    type="text"
                                    name="recipient_fields[{{ $field['key'] }}]"
                                    maxlength="191"
                                    value="{{ old('recipient_fields.'.$field['key']) }}"
                                    placeholder="{{ $field['placeholder'] }}"
                                    autocomplete="off"
                                    data-recipient-input
                                    data-required="{{ $field['required'] ? 'true' : 'false' }}"
                                    @disabled((string) $initialGame !== (string) $game->id || $initialPurchaseMode !== 'single')
                                    @if ($field['required'] && (string) $initialGame === (string) $game->id && $initialPurchaseMode === 'single') required @endif
                                >
                                @if ($loop->first)<p class="home-field-help">Nhập đúng thông tin để hệ thống xử lý tự động.</p>@endif
                                @error('recipient_fields.'.$field['key'])<p class="home-field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach

                        <div class="home-field home-quantity-field">
                            <label for="topup-single-quantity">Số lượng</label>
                            <div class="home-quantity-control">
                                <button type="button" data-quantity-decrease aria-label="Giảm số lượng">−</button>
                                <input id="topup-single-quantity" type="number" name="single_quantity" min="1" max="100" value="{{ $initialSingleQuantity }}" inputmode="numeric" data-single-quantity required @disabled($initialPurchaseMode !== 'single')>
                                <button type="button" data-quantity-increase aria-label="Tăng số lượng">+</button>
                            </div>
                            @error('single_quantity')<p class="home-field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach
                @error('recipient_fields')<p class="home-field-error">{{ $message }}</p>@enderror
            </div>

            <div id="purchase-panel-bulk" role="tabpanel" aria-labelledby="purchase-tab-bulk" data-purchase-panel="bulk" @if ($initialPurchaseMode !== 'bulk') hidden @endif>
                <div class="home-field">
                    <label class="inline-flex items-center gap-1.5" for="topup-server-bulk"><i class="bx bx-server text-lg text-cyan-700" aria-hidden="true"></i>Máy chủ áp dụng cho danh sách <span aria-hidden="true">*</span></label>
                    <select id="topup-server-bulk" class="client-input" data-server-picker="bulk" @disabled($initialPurchaseMode !== 'bulk') @if ($initialPurchaseMode === 'bulk') required @endif>
                        <option value="">Chọn máy chủ</option>
                        @foreach ($games as $game)
                            @foreach ($game->servers as $server)
                                <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <p class="home-field-help">Máy chủ này được áp dụng cho toàn bộ tài khoản bên dưới.</p>
                    @error('server_id')<p class="home-field-error">{{ $message }}</p>@enderror
                </div>

                <label class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-800" for="topup-bulk-recipients"><i class="bx bx-list text-lg text-cyan-700" aria-hidden="true"></i>Danh sách tài khoản</label>
                @foreach ($games as $game)
                    @php
                        $checkoutFields = collect($game->checkoutFields());
                        $fieldLabels = $checkoutFields->pluck('label')->push('Số lượng')->implode(' | ');
                        $fieldPlaceholders = $checkoutFields
                            ->map(fn ($field) => filled($field['placeholder'] ?? null) ? $field['placeholder'] : $field['label'])
                            ->push('Số lượng')
                            ->implode('|');
                    @endphp
                    <p class="home-bulk-schema" data-bulk-schema="{{ $game->id }}" data-bulk-placeholder="{{ $fieldPlaceholders }}" @if ((string) $initialGame !== (string) $game->id) hidden @endif>
                        Mỗi dòng theo thứ tự: <strong>{{ $fieldLabels }}</strong>
                    </p>
                @endforeach
                <textarea
                    id="topup-bulk-recipients"
                    class="client-input home-bulk-textarea"
                    name="bulk_recipients"
                    rows="6"
                    maxlength="25000"
                    placeholder="{{ $initialBulkPlaceholder }}"
                    data-bulk-recipients
                    @disabled($initialPurchaseMode !== 'bulk')
                    @if ($initialPurchaseMode === 'bulk') required @endif
                >{{ old('bulk_recipients') }}</textarea>
                <div class="home-bulk-summary">
                    <span>Dòng trống được bỏ qua.</span>
                    <strong><span data-bulk-account-count>{{ $initialBulkRecipientCount }}</span> tài khoản · Tổng số lượng: <span data-bulk-count>{{ $initialBulkQuantity }}</span></strong>
                </div>
                @error('bulk_recipients')<p class="home-field-error">{{ $message }}</p>@enderror
            </div>

            @guest
                <div class="home-field">
                    <label class="inline-flex items-center gap-1.5" for="topup-email"><i class="bx bx-envelope text-lg text-cyan-700" aria-hidden="true"></i>Email nhận trạng thái <span aria-hidden="true">*</span></label>
                    <input id="topup-email" class="client-input" type="email" name="email" value="{{ old('email') }}" required placeholder="email@example.com">
                    <p class="home-field-help">Dùng để gửi trạng thái và tra cứu đơn hàng.</p>
                    @error('email')<p class="home-field-error">{{ $message }}</p>@enderror
                </div>
            @endguest
        </div>

        <aside class="home-order-summary" aria-labelledby="order-summary-title">
            <h2 id="order-summary-title" class="flex items-center gap-2"><i class="bx bx-receipt text-xl text-cyan-700" aria-hidden="true"></i>Tóm tắt đơn</h2>
            <dl class="home-summary-list">
                <div><dt>Gói nạp</dt><dd data-summary-package>{{ $initialPackage?->denomination ? number_format($initialPackage->denomination, 0, ',', '.').'đ' : ($initialPackage?->name ?? 'Chưa chọn') }}</dd></div>
                <div><dt>Số lượng</dt><dd data-summary-quantity>{{ $initialQuantity }}</dd></div>
                <div><dt>Giá gốc</dt><dd data-summary-original>{{ number_format($initialOriginalTotal, 0, ',', '.') }}đ</dd></div>
                <div class="home-summary-discount"><dt>Chiết khấu</dt><dd data-order-discount>-{{ number_format($initialDiscountTotal, 0, ',', '.') }}đ</dd></div>
                <div class="home-summary-total"><dt>Thanh toán</dt><dd data-order-total>{{ number_format($initialPaymentTotal, 0, ',', '.') }}đ</dd></div>
            </dl>

            <div class="home-summary-rewards" data-summary-rewards @if (! $initialPackage) hidden @endif>
                <p class="flex items-center gap-1.5"><i class="bx bx-gift text-base text-cyan-700" aria-hidden="true"></i>Dự kiến nhận trong game</p>
                <span data-summary-reward>{{ $initialPackage?->carot_amount !== null ? number_format($initialPackage->carot_amount * $initialQuantity, 0, ',', '.').' '.($initialGameModel?->reward_label ?: 'Thực nhận') : 'Đang cập nhật' }}</span>
                <span data-summary-reward-x2 @if ($initialPackage?->reward_x2_amount === null) hidden @endif>KM X2: {{ number_format((int) $initialPackage?->reward_x2_amount * $initialQuantity, 0, ',', '.') }}</span>
                <span data-summary-reward-x3 @if ($initialPackage?->reward_x3_amount === null) hidden @endif>KM X3: {{ number_format((int) $initialPackage?->reward_x3_amount * $initialQuantity, 0, ',', '.') }}</span>
            </div>

            <div class="home-field home-payment-field">
                <label class="inline-flex items-center gap-1.5" for="topup-payment"><i class="bx bx-credit-card text-lg text-cyan-700" aria-hidden="true"></i>Phương thức thanh toán</label>
                <select id="topup-payment" name="payment_method" class="client-input" data-payment-method data-payment-explicit="{{ old('payment_method') ? 'true' : 'false' }}" aria-describedby="topup-payment-help">
                    <option value="bank_transfer" @selected($initialPaymentMethod === 'bank_transfer')>Chuyển khoản ngân hàng / QR tự động</option>
                    @auth
                        <option value="wallet" data-wallet-balance="{{ (int) $walletBalance }}" @selected($initialPaymentMethod === 'wallet') @disabled(! $initialCanPayWithWallet)>Số dư ví · {{ number_format((int) $walletBalance, 0, ',', '.') }}đ</option>
                    @endauth
                </select>
                <p id="topup-payment-help" class="home-field-help" data-payment-help aria-live="polite">
                    @if ($initialCanPayWithWallet && $initialPaymentMethod === 'wallet')
                        Số dư ví đủ nên hệ thống đang ưu tiên thanh toán bằng ví. Bạn vẫn có thể chọn ATM.
                    @elseif ($initialCanPayWithWallet)
                        Bạn đang chọn thanh toán qua ngân hàng / ATM.
                    @elseif (auth()->check())
                        Số dư ví chưa đủ, đơn hàng sẽ thanh toán qua ngân hàng / ATM.
                    @else
                        Đăng nhập và nạp số dư để thanh toán tự động bằng ví.
                    @endif
                </p>
                @error('payment_method')<p class="home-field-error">{{ $message }}</p>@enderror
            </div>

            <button class="home-checkout-submit" type="submit" data-submit-button @disabled(! $initialCanSubmit)>
                <i class="bx bx-bolt text-xl" aria-hidden="true"></i>
                <span data-submit-text>{{ ! $initialPackage ? 'CHỌN GÓI NẠP' : ($initialCanSubmit ? 'NẠP NGAY '.number_format($initialPaymentTotal, 0, ',', '.').'đ' : ($initialQuantity === 0 ? 'NHẬP DANH SÁCH TÀI KHOẢN' : 'KIỂM TRA SỐ LƯỢNG')) }}</span>
            </button>
            <p class="home-checkout-note">Hệ thống không yêu cầu cung cấp mật khẩu game.</p>
        </aside>
    </div>
</form>
