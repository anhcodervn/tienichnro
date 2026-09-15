@php
    $selectedGame = $selectedGame ?? null;
    $walletBalance = $walletBalance ?? 0;
    $turnstileEnabled = (bool) ($turnstileEnabled ?? false);
    $turnstileSiteKey = (string) ($turnstileSiteKey ?? '');
    $showConfirmation = (bool) ($showConfirmation ?? false);
    $useH1 = (bool) ($useH1 ?? ! $selectedGame);
    $affiliateReferrerUsername = (string) ($affiliateReferrerUsername ?? '');
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
    $initialBulkQuantitiesAreValid = $initialBulkLines->isNotEmpty()
        && $initialBulkLines->every(function ($line) use ($initialPackage): bool {
            if (! $initialPackage) {
                return false;
            }

            $values = explode('|', $line);
            $quantity = trim((string) end($values));

            return preg_match('/^[1-9]\d*$/D', $quantity) === 1
                && (int) $quantity >= (int) $initialPackage->min_quantity
                && (int) $quantity <= min(10, (int) ($initialPackage->max_quantity ?: 10));
        });
    $initialCanSubmit = $initialPackage && ($initialPurchaseMode === 'bulk'
        ? $initialBulkQuantitiesAreValid
        : $initialQuantity >= (int) $initialPackage->min_quantity
            && $initialQuantity <= min(10, (int) ($initialPackage->max_quantity ?: 10)));
@endphp

<form method="POST" action="{{ route('checkout.store') }}" class="home-checkout-card" data-topup-form>
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <input type="hidden" name="purchase_mode" value="{{ $initialPurchaseMode }}" data-purchase-mode>

    <header class="home-checkout-header">
        <div>
            <p class="home-checkout-eyebrow inline-flex items-center gap-1.5"><i class="bx bx-bolt text-base" aria-hidden="true"></i>Nạp game tự động</p>
            @if ($selectedGame || ! $useH1)
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
                        <option value="{{ $game->id }}" data-name="{{ $game->name }}" @selected((string) $initialGame === (string) $game->id)>{{ $game->name }} - ID: {{ $game->id }}</option>
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
                                data-name="{{ $package->name }}"
                                data-denomination="{{ (int) ($package->denomination ?? $package->original_price) }}"
                                data-original="{{ (int) $package->original_price }}"
                                data-retail="{{ (int) ($package->retail_price ?? $package->price) }}"
                                data-price="{{ (int) $package->price }}"
                                data-discount="{{ (float) $package->discount_percent }}"
                                data-min="{{ $package->min_quantity }}"
                                data-max="{{ min(10, (int) ($package->max_quantity ?: 10)) }}"
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
            </fieldset>

            <select name="server_id" data-server-source hidden>
                <option value="">Chọn máy chủ</option>
                @foreach ($games as $game)
                    @foreach ($game->servers as $server)
                        <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }} - ID: {{ $server->id }}</option>
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
                ><i class="bx bx-user text-lg" aria-hidden="true"></i><span>Nạp 1 acc</span></button>
                <button
                    id="purchase-tab-bulk"
                    type="button"
                    role="tab"
                    data-purchase-tab="bulk"
                    aria-controls="purchase-panel-bulk"
                    aria-selected="{{ $initialPurchaseMode === 'bulk' ? 'true' : 'false' }}"
                    tabindex="{{ $initialPurchaseMode === 'bulk' ? '0' : '-1' }}"
                ><i class="bx bx-group text-lg" aria-hidden="true"></i><span>Nạp nhiều acc</span></button>
            </div>
            @error('purchase_mode')<p class="home-field-error">{{ $message }}</p>@enderror

            <div id="purchase-panel-single" role="tabpanel" aria-labelledby="purchase-tab-single" data-purchase-panel="single" @if ($initialPurchaseMode !== 'single') hidden @endif>
                <div class="home-field">
                    <label class="inline-flex items-center gap-1.5" for="topup-server-single"><i class="bx bx-server text-lg text-cyan-700" aria-hidden="true"></i>Máy chủ <span aria-hidden="true">*</span></label>
                    <select id="topup-server-single" class="client-input" data-server-picker="single" @disabled($initialPurchaseMode !== 'single') @if ($initialPurchaseMode === 'single') required @endif>
                        <option value="">Chọn máy chủ</option>
                        @foreach ($games as $game)
                            @foreach ($game->servers as $server)
                                <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }} - ID: {{ $server->id }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('server_id')<p class="home-field-error">{{ $message }}</p>@enderror
                </div>

                @foreach ($games as $game)
                    <div class="home-recipient-grid" data-recipient-fields="{{ $game->id }}" data-single-confirm-recipient-label="{{ collect($game->checkoutFields())->pluck('label')->implode(' | ') }}" @if ((string) $initialGame !== (string) $game->id) hidden @endif>
                        @foreach ($game->checkoutFields() as $field)
                            <div class="home-field home-recipient-field sm:col-span-2" data-field-key="{{ $field['key'] }}">
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
                                    autocapitalize="none"
                                    spellcheck="false"
                                    data-recipient-input
                                    data-required="{{ $field['required'] ? 'true' : 'false' }}"
                                    data-validation-regex="{{ $field['regex'] }}"
                                    data-validation-label="{{ $field['label'] }}"
                                    @disabled((string) $initialGame !== (string) $game->id || $initialPurchaseMode !== 'single')
                                    @if ($field['required'] && (string) $initialGame === (string) $game->id && $initialPurchaseMode === 'single') required @endif
                                >
                                @if ($loop->first)<p class="home-field-help">Nhập đúng thông tin để hệ thống xử lý tự động.</p>@endif
                                <p class="home-field-error" data-recipient-format-error role="alert" aria-live="polite" hidden></p>
                                @error('recipient_fields.'.$field['key'])<p class="home-field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach

                        <div class="home-field home-quantity-field">
                            <label for="topup-single-quantity">Số lượng</label>
                            <div class="home-quantity-control">
                                <button type="button" data-quantity-decrease aria-label="Giảm số lượng">−</button>
                                <input id="topup-single-quantity" type="number" name="single_quantity" min="1" max="10" value="{{ $initialSingleQuantity }}" inputmode="numeric" data-single-quantity required @disabled($initialPurchaseMode !== 'single')>
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
                                <option value="{{ $server->id }}" data-game="{{ $game->id }}" @selected((string) old('server_id') === (string) $server->id)>{{ $server->name }} - ID: {{ $server->id }}</option>
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
                        $fieldLabels = $checkoutFields->pluck('label')->push('Số lượng thẻ')->implode(' | ');
                        $fieldPlaceholders = $checkoutFields
                            ->map(fn ($field) => filled($field['placeholder'] ?? null) ? $field['placeholder'] : $field['label'])
                            ->push('Số lượng thẻ')
                            ->implode('|');
                    @endphp
                    <p class="home-bulk-schema" data-bulk-schema="{{ $game->id }}" data-bulk-placeholder="{{ $fieldPlaceholders }}" data-bulk-confirm-recipient-label="{{ $fieldLabels }}" data-bulk-fields="{{ $checkoutFields->map(fn ($field) => ['label' => $field['label'], 'required' => $field['required'], 'regex' => $field['regex']])->values()->toJson() }}" @if ((string) $initialGame !== (string) $game->id) hidden @endif>
                        Mỗi dòng 1 tài khoản theo đúng định dạng: <strong>{{ $fieldLabels }}</strong>
                    </p>
                @endforeach
                <textarea
                    id="topup-bulk-recipients"
                    class="client-input home-bulk-textarea"
                    name="bulk_recipients"
                    rows="6"
                    maxlength="25000"
                    placeholder="{{ $initialBulkPlaceholder }}"
                    autocapitalize="none"
                    spellcheck="false"
                    data-bulk-recipients
                    @disabled($initialPurchaseMode !== 'bulk')
                    @if ($initialPurchaseMode === 'bulk') required @endif
                >{{ old('bulk_recipients') }}</textarea>
                <div class="home-bulk-summary">
                    <span>Dòng trống được bỏ qua.</span>
                    <strong><span data-bulk-account-count>{{ $initialBulkRecipientCount }}</span> tài khoản · Tổng số thẻ: <span data-bulk-count>{{ $initialBulkQuantity }}</span></strong>
                </div>
                <p class="home-field-error" data-bulk-format-error role="alert" aria-live="polite" hidden></p>
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
                <div><dt data-summary-quantity-label>{{ $initialPurchaseMode === 'bulk' ? 'Tổng số thẻ' : 'Số lượng thẻ' }}</dt><dd data-summary-quantity>{{ $initialQuantity }}</dd></div>
                <div><dt>Giá gốc</dt><dd data-summary-original>{{ number_format($initialOriginalTotal, 0, ',', '.') }}đ</dd></div>
                <div class="home-summary-discount"><dt>Chiết khấu</dt><dd data-order-discount>-{{ number_format($initialDiscountTotal, 0, ',', '.') }}đ</dd></div>
                <div class="home-summary-total"><dt>Thanh toán</dt><dd data-order-total>{{ number_format($initialPaymentTotal, 0, ',', '.') }}đ</dd></div>
            </dl>

            <div class="home-summary-rewards" data-summary-rewards @if (! $initialPackage) hidden @endif>
                <p class="flex items-center gap-1.5"><i class="bx bx-gift text-base text-cyan-700" aria-hidden="true"></i>Dự kiến nhận trong game</p>
                <span data-summary-reward>{{ $initialPackage?->rewardDisplay('base_amount', $initialQuantity, $initialGameModel?->reward_label) ?? 'Đang cập nhật' }}</span>
                <span data-summary-reward-x2 @if ($initialPackage?->rewardDisplay('reward_x2_amount', $initialQuantity, $initialGameModel?->reward_label) === null) hidden @endif>KM X2: {{ $initialPackage?->rewardDisplay('reward_x2_amount', $initialQuantity, $initialGameModel?->reward_label) }}</span>
                <span data-summary-reward-x3 @if ($initialPackage?->rewardDisplay('reward_x3_amount', $initialQuantity, $initialGameModel?->reward_label) === null) hidden @endif>KM X3: {{ $initialPackage?->rewardDisplay('reward_x3_amount', $initialQuantity, $initialGameModel?->reward_label) }}</span>
            </div>

            <fieldset class="home-field home-payment-field">
                <legend id="topup-payment-label" class="inline-flex items-center gap-1.5"><i class="bx bx-credit-card text-lg text-cyan-700" aria-hidden="true"></i>Phương thức thanh toán</legend>
                <select id="topup-payment" name="payment_method" data-payment-method data-payment-explicit="{{ old('payment_method') ? 'true' : 'false' }}" aria-hidden="true" tabindex="-1" hidden>
                    <option value="bank_transfer" @selected($initialPaymentMethod === 'bank_transfer')>Chuyển khoản ngân hàng / QR tự động</option>
                    @auth
                        <option value="wallet" data-wallet-balance="{{ (int) $walletBalance }}" @selected($initialPaymentMethod === 'wallet') @disabled(! $initialCanPayWithWallet)>Số dư ví · {{ number_format((int) $walletBalance, 0, ',', '.') }}đ</option>
                    @endauth
                </select>
                <div class="grid grid-cols-2 gap-2" role="radiogroup" aria-labelledby="topup-payment-label" aria-describedby="topup-payment-help" data-payment-options>
                    <button
                        type="button"
                        class="home-payment-option"
                        role="radio"
                        aria-checked="{{ $initialPaymentMethod === 'wallet' ? 'true' : 'false' }}"
                        tabindex="{{ $initialPaymentMethod === 'wallet' ? '0' : '-1' }}"
                        data-payment-option="wallet"
                        @disabled(! $initialCanPayWithWallet)
                    >
                        <i class="bx bx-wallet-alt text-xl" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <strong class="block truncate">Số dư tài khoản</strong>
                            <small class="block truncate font-semibold opacity-75">
                                @auth
                                    {{ number_format((int) $walletBalance, 0, ',', '.') }}đ
                                @else
                                    Cần đăng nhập
                                @endauth
                            </small>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="home-payment-option"
                        role="radio"
                        aria-checked="{{ $initialPaymentMethod === 'bank_transfer' ? 'true' : 'false' }}"
                        tabindex="{{ $initialPaymentMethod === 'bank_transfer' ? '0' : '-1' }}"
                        data-payment-option="bank_transfer"
                    >
                        <i class="bx bx-qr-scan text-xl" aria-hidden="true"></i>
                        <span class="min-w-0">
                            <strong class="block truncate">QR thanh toán</strong>
                            <small class="block truncate font-semibold opacity-75">Chuyển khoản tự động</small>
                        </span>
                    </button>
                </div>
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
            </fieldset>

            @guest
                @if ($turnstileEnabled && $turnstileSiteKey !== '')
                    <div class="home-field" data-turnstile-checkout>
                        <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-action="guest_checkout" data-theme="light"></div>
                        <p class="home-field-help">Xác minh bạn không phải bot trước khi tạo đơn.</p>
                        @error('cf-turnstile-response')<p class="home-field-error">{{ $message }}</p>@enderror
                    </div>
                    @once
                        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer data-turnstile-script></script>
                    @endonce
                @endif
            @endguest

            <button class="home-checkout-submit" type="submit" data-submit-button @disabled(! $initialCanSubmit)>
                <i class="bx bx-bolt text-xl" aria-hidden="true"></i>
                <span data-submit-text>{{ ! $initialPackage ? 'CHỌN GÓI NẠP' : ($initialCanSubmit ? 'NẠP NGAY '.number_format($initialPaymentTotal, 0, ',', '.').'đ' : ($initialQuantity === 0 ? 'NHẬP DANH SÁCH TÀI KHOẢN' : 'KIỂM TRA SỐ LƯỢNG')) }}</span>
            </button>
            <p class="home-checkout-note">Hệ thống không yêu cầu cung cấp mật khẩu game.</p>
        </aside>
    </div>

    @if ($showConfirmation)
    <div class="fixed inset-0 z-[80]" data-topup-confirmation-modal aria-hidden="true" hidden>
        <button class="absolute inset-0 bg-slate-950/60 backdrop-blur-[1px]" type="button" data-topup-confirmation-close tabindex="-1" aria-label="Đóng bước xác nhận nạp"></button>
        <section class="absolute inset-x-3 top-1/2 flex max-h-[calc(100dvh-1.5rem)] -translate-y-1/2 flex-col overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-2xl sm:inset-x-6 sm:mx-auto sm:max-w-2xl" role="dialog" aria-modal="true" aria-labelledby="topup-confirmation-title" tabindex="-1" data-topup-confirmation-panel>
            <header class="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 sm:px-5">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-700">Kiểm tra trước khi nạp</p>
                    <h2 class="text-lg font-extrabold text-slate-950" id="topup-confirmation-title">Xác nhận thông tin nạp</h2>
                </div>
                <button class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] border border-slate-300 text-slate-600 transition hover:border-rose-300 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-700" type="button" data-topup-confirmation-close aria-label="Đóng"><i class="bx bx-x text-2xl" aria-hidden="true"></i></button>
            </header>

            <div class="grid gap-4 overflow-y-auto p-4 sm:p-5">
                <dl class="grid gap-3 rounded-[5px] border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div class="grid gap-1 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-4"><dt class="font-semibold text-slate-500">Game</dt><dd class="break-words font-extrabold text-slate-950" data-confirm-game></dd></div>
                    <div class="grid gap-1 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-4"><dt class="font-semibold text-slate-500">Mệnh giá nạp</dt><dd class="break-words font-extrabold text-slate-950" data-confirm-package></dd></div>
                    <div class="grid gap-1 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-4"><dt class="font-semibold text-slate-500">Máy chủ</dt><dd class="break-words font-extrabold text-slate-950" data-confirm-server></dd></div>
                    <div class="grid gap-1 border-t border-slate-200 pt-3 sm:grid-cols-[9rem_minmax(0,1fr)] sm:gap-4">
                        <dt class="font-semibold text-slate-500">Tài khoản nạp</dt>
                        <dd class="grid min-w-0 gap-2">
                            <p id="topup-confirm-recipient-label" class="break-words text-sm font-semibold text-slate-700" data-confirm-recipient-label></p>
                            <ul class="grid gap-2 break-words font-mono text-sm font-bold text-slate-950" data-confirm-recipients aria-labelledby="topup-confirm-recipient-label"></ul>
                        </dd>
                    </div>
                    <div class="grid gap-1 border-t border-slate-300 pt-3 sm:grid-cols-[9rem_minmax(0,1fr)] sm:items-center sm:gap-4"><dt class="font-bold text-slate-800">Tổng thanh toán</dt><dd class="text-xl font-extrabold text-cyan-800" data-confirm-total></dd></div>
                </dl>

                <div class="flex items-start gap-3 rounded-[5px] border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                    <i class="bx bx-error-circle mt-0.5 shrink-0 text-xl text-amber-700" aria-hidden="true"></i>
                    <div class="grid gap-2">
                        <p><strong>Chú ý:</strong> Hãy kiểm tra lại game và tài khoản nạp. Tùy theo game, thông tin này sẽ là tài khoản đăng nhập game hoặc tên nhân vật game. Hãy nhập đúng theo game bạn đang cần nạp.</p>
                        @if ($affiliateReferrerUsername !== '')
                            <p data-affiliate-referrer><strong>Người giới thiệu:</strong> <span class="font-extrabold text-cyan-800">{{ '@'.$affiliateReferrerUsername }}</span></p>
                        @endif
                    </div>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-[5px] border border-slate-300 bg-white p-4 text-sm font-bold text-slate-900 transition hover:border-cyan-500">
                    <input class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-400 text-cyan-700 focus:ring-cyan-600" type="checkbox" data-topup-confirmation-checkbox>
                    <span>Tôi đã kiểm tra kỹ thông tin</span>
                </label>
            </div>

            <footer class="grid gap-2 border-t border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 sm:px-5">
                <button class="client-button-secondary min-h-12 bg-white" type="button" data-topup-confirmation-close>Kiểm tra lại</button>
                <button class="home-checkout-submit m-0 min-h-12" type="button" data-topup-confirmation-submit disabled>
                    <i class="bx bx-bolt text-xl" aria-hidden="true"></i>
                    <span data-topup-confirmation-submit-text>NẠP NGAY</span>
                </button>
            </footer>
        </section>
    </div>
    @endif
</form>
