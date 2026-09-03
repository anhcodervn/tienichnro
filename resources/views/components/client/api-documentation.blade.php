@props(['documentation'])

@php
    $balanceEndpoint = $documentation['balance_endpoint'];
    $catalogEndpoint = $documentation['catalog_endpoint'];
    $createOrderEndpoint = $documentation['create_order_endpoint'];
    $orderStatusEndpoint = $documentation['order_status_endpoint'];
    $maxRecipients = $documentation['max_recipients'];
    $maxQuantity = $documentation['max_quantity_per_recipient'];
    $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $headers = "--header 'Accept: application/json' \\\n  --header 'X-API-KEY: YOUR_API_KEY' \\\n  --header 'X-API-SECRET: YOUR_API_SECRET'";
    $balanceCurl = "curl --request GET '{$balanceEndpoint}' \\\n  {$headers}";
    $catalogCurl = "curl --request GET '{$catalogEndpoint}' \\\n  {$headers}";
    $createRequest = json_encode([
        'request_id' => '550e8400-e29b-41d4-a716-446655440000',
        'game' => 1,
        'server' => 2,
        'price' => 10000,
        'payload' => [
            ['account' => 'player-one', 'character' => 'Hero One', 'amount' => 2],
            ['account' => 'player-two', 'character' => 'Hero Two', 'amount' => 1],
        ],
    ], $jsonFlags);
    $createOrderCurl = "curl --request POST '{$createOrderEndpoint}' \\\n  {$headers} \\\n  --header 'Content-Type: application/json' \\\n  --data '{$createRequest}'";
    $orderStatusCurl = "curl --request GET '{$orderStatusEndpoint}' \\\n  {$headers}";
    $balanceResponse = json_encode([
        'status' => true,
        'data' => ['balance' => 200000, 'currency' => 'VND'],
    ], $jsonFlags);
    $catalogResponse = json_encode([
        'status' => true,
        'data' => [[
            'id' => 1,
            'name' => 'Ngọc Rồng Online',
            'slug' => 'ngoc-rong-online',
            'short_name' => 'NRO',
            'payload_fields' => [
                ['key' => 'account', 'label' => 'Tài khoản', 'placeholder' => 'Nhập tài khoản', 'required' => true],
                ['key' => 'character', 'label' => 'Tên nhân vật', 'placeholder' => 'Nhập tên nhân vật', 'required' => true],
            ],
            'servers' => [
                ['id' => 2, 'name' => 'Vũ trụ 2'],
            ],
            'packages' => [[
                'id' => 3,
                'name' => 'Gói 10.000đ',
                'price' => 10000,
                'sale_price' => 8500,
                'retail_price' => 9000,
                'package_source' => 'global',
                'original_price' => 10000,
                'min_amount' => 1,
                'max_amount' => 10,
                'currency' => 'VND',
            ]],
        ]],
    ], $jsonFlags);
    $orderResponse = json_encode([
        'status' => true,
        'data' => [
            'order_id' => 'TOP260903ABCDEF',
            'request_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'pending',
            'payment_status' => 'paid',
            'game' => ['id' => 1, 'name' => 'Ngọc Rồng Online'],
            'server' => ['id' => 2, 'name' => 'Vũ trụ 2'],
            'package' => ['id' => 3, 'name' => 'Gói 10.000đ', 'price' => 10000, 'sale_price' => 8500],
            'total' => 25500,
            'currency' => 'VND',
            'payload' => [
                ['account' => 'player-one', 'character' => 'Hero One', 'amount' => 2, 'status' => 'pending', 'failure_reason' => null],
                ['account' => 'player-two', 'character' => 'Hero Two', 'amount' => 1, 'status' => 'pending', 'failure_reason' => null],
            ],
            'failure_reason' => null,
            'created_at' => '2026-09-03T10:00:00+07:00',
            'updated_at' => '2026-09-03T10:00:00+07:00',
            'processing_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ],
    ], $jsonFlags);
    $completedOrderResponse = json_encode([
        'status' => true,
        'data' => [
            'order_id' => 'TOP260903ABCDEF',
            'request_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'completed',
            'payment_status' => 'paid',
            'game' => ['id' => 1, 'name' => 'Ngọc Rồng Online'],
            'server' => ['id' => 2, 'name' => 'Vũ trụ 2'],
            'package' => ['id' => 3, 'name' => 'Gói 10.000đ', 'price' => 10000, 'sale_price' => 8500],
            'total' => 25500,
            'currency' => 'VND',
            'payload' => [
                ['account' => 'player-one', 'character' => 'Hero One', 'amount' => 2, 'status' => 'completed', 'failure_reason' => null],
                ['account' => 'player-two', 'character' => 'Hero Two', 'amount' => 1, 'status' => 'completed', 'failure_reason' => null],
            ],
            'failure_reason' => null,
            'created_at' => '2026-09-03T10:00:00+07:00',
            'updated_at' => '2026-09-03T10:01:30+07:00',
            'processing_at' => '2026-09-03T10:00:03+07:00',
            'completed_at' => '2026-09-03T10:01:30+07:00',
            'failed_at' => null,
        ],
    ], $jsonFlags);
    $authenticationError = json_encode([
        'status' => false,
        'message' => 'API key hoặc API secret không hợp lệ.',
    ], $jsonFlags);
    $validationError = json_encode([
        'status' => false,
        'message' => 'Không tìm thấy gói nạp phù hợp với game và mệnh giá đã chọn.',
    ], $jsonFlags);
@endphp

<div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
    <main class="grid min-w-0 content-start gap-5">
        <header class="rounded-[8px] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-700">REST API v1</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Tài liệu API nạp game</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Thực hiện lần lượt từ lấy catalog, tạo đơn đến kiểm tra trạng thái. Mọi ví dụ đều hiển thị đầy đủ request cURL và response JSON thực tế.</p>
        </header>

        <section id="authentication" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 bg-slate-50 p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-cyan-700">Thiết lập chung</p>
                <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Xác thực và Base URL</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Mọi request đều gửi hai header bên dưới. API không sử dụng Bearer token.</p>
            </div>
            <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-2">
                <div class="grid content-start gap-3">
                    <div class="rounded-[6px] border-2 border-slate-300 bg-white p-4">
                        <span class="text-xs font-bold uppercase tracking-wide text-slate-500">Base URL</span>
                        <code class="mt-2 block break-all text-sm font-bold text-cyan-700">{{ $documentation['base_url'] }}</code>
                    </div>
                    <dl class="grid gap-2 text-sm">
                        <div class="grid gap-1 rounded-[6px] border border-slate-200 p-3 sm:grid-cols-[9rem_minmax(0,1fr)]">
                            <dt class="font-mono font-bold text-slate-950">X-API-KEY</dt><dd class="text-slate-600">Khóa công khai dùng để định danh tài khoản.</dd>
                        </div>
                        <div class="grid gap-1 rounded-[6px] border border-slate-200 p-3 sm:grid-cols-[9rem_minmax(0,1fr)]">
                            <dt class="font-mono font-bold text-slate-950">X-API-SECRET</dt><dd class="text-slate-600">Mã bí mật chỉ hiển thị một lần khi tạo key.</dd>
                        </div>
                    </dl>
                </div>
                <x-client.api-code-block title="Header mẫu" :code="$headers" copyable />
            </div>
        </section>

        <section id="balance" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/balance</code></div>
                <h2 class="mt-3 text-xl font-extrabold text-slate-950">Kiểm tra số dư</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Trả về số dư ví hiện có của đúng tài khoản sở hữu API key.</p>
            </div>
            <div class="grid gap-4 p-5 sm:p-6 lg:grid-cols-2">
                <x-client.api-code-block title="cURL request" :code="$balanceCurl" copyable />
                <x-client.api-code-block title="Response 200" :code="$balanceResponse" copyable />
            </div>
        </section>

        <section id="catalog" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/catalog</code></div>
                <h2 class="mt-3 text-xl font-extrabold text-slate-950">Lấy danh mục game và bảng giá</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600"><code>payload_fields</code> cho biết form người nhận cần những trường nào. Dùng <code>game.id</code>, <code>servers[].id</code> và <code>packages[].price</code> khi tạo đơn.</p>
                <div class="mt-3 rounded-[6px] border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm leading-6 text-cyan-950"><strong>Giá cần thanh toán:</strong> <code>sale_price</code> đã bao gồm chiết khấu riêng của tài khoản API. Không tự tính lại giá ở hệ thống của bạn.</div>
            </div>
            <div class="grid gap-4 p-5 sm:p-6 2xl:grid-cols-2">
                <x-client.api-code-block title="cURL request" :code="$catalogCurl" copyable />
                <x-client.api-code-block title="Response 200" :code="$catalogResponse" copyable />
            </div>
        </section>

        <section id="create-order" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-indigo-100 px-2.5 py-1 text-xs font-black text-indigo-700">POST</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/orders</code></div>
                <h2 class="mt-3 text-xl font-extrabold text-slate-950">Tạo đơn nạp</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600"><code>price</code> là mệnh giá thẻ lấy từ catalog, không phải giá bán. Server tự chọn gói, áp dụng chiết khấu và trừ ví.</p>
            </div>
            <div class="grid gap-5 p-5 sm:p-6">
                <div class="overflow-x-auto rounded-[6px] border border-slate-200">
                    <table class="w-full min-w-[48rem] text-left text-sm">
                        <thead class="bg-slate-100 text-xs font-bold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">Field</th><th class="px-4 py-3">Bắt buộc</th><th class="px-4 py-3">Kiểu</th><th class="px-4 py-3">Mô tả</th></tr></thead>
                        <tbody class="divide-y divide-slate-200">
                            @foreach ([
                                ['request_id', 'Có', 'UUID', 'Mã duy nhất do bạn tạo để chống trùng đơn. Gửi lại cùng request_id sẽ nhận lại đơn cũ.'],
                                ['game', 'Có', 'integer', 'ID game từ GET /catalog.'],
                                ['server', 'Có', 'integer', 'ID server thuộc đúng game đã chọn.'],
                                ['price', 'Có', 'integer', 'Mệnh giá packages[].price từ catalog, ví dụ 10000.'],
                                ['payload', 'Có', 'array', "Danh sách 1–{$maxRecipients} người nhận."],
                                ['payload.*.amount', 'Có', 'integer', "Số lượng gói cho người nhận, từ 1–{$maxQuantity}."],
                                ['payload.*.{field}', 'Có', 'string', 'Các trường động theo payload_fields của game, ví dụ account hoặc character.'],
                            ] as [$field, $required, $type, $description])
                                <tr><td class="px-4 py-3 font-mono font-bold text-slate-950">{{ $field }}</td><td class="px-4 py-3"><span class="rounded bg-rose-50 px-2 py-1 text-xs font-bold text-rose-700">{{ $required }}</span></td><td class="px-4 py-3 text-slate-700">{{ $type }}</td><td class="px-4 py-3 leading-6 text-slate-600">{{ $description }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="grid gap-4 2xl:grid-cols-2">
                    <x-client.api-code-block title="Request JSON" :code="$createRequest" copyable />
                    <x-client.api-code-block title="cURL request" :code="$createOrderCurl" copyable />
                </div>
                <x-client.api-code-block title="Response 201 — tạo đơn thành công" :code="$orderResponse" copyable />
                <div class="rounded-[6px] border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">
                    <strong>Chống tạo trùng:</strong> lần đầu trả HTTP <code>201</code>. Nếu gửi lại đúng <code>request_id</code> bằng cùng tài khoản, API trả HTTP <code>200</code> và dữ liệu đơn đã tạo, không trừ ví lần hai.
                </div>
            </div>
        </section>

        <section id="order-status" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/orders/{order_id}</code></div>
                <h2 class="mt-3 text-xl font-extrabold text-slate-950">Kiểm tra trạng thái đơn</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Thay <code>ORDER_ID</code> bằng <code>order_id</code> nhận từ API tạo đơn. Chỉ tài khoản tạo đơn mới truy vấn được.</p>
            </div>
            <div class="grid gap-4 p-5 sm:p-6 2xl:grid-cols-2">
                <x-client.api-code-block title="cURL request" :code="$orderStatusCurl" copyable />
                <x-client.api-code-block title="Response 200 — hoàn tất" :code="$completedOrderResponse" copyable />
            </div>
            <div class="border-t border-slate-200 bg-slate-50 p-5 sm:p-6">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([
                        ['pending', 'Đã nhận đơn, đang chờ xử lý', 'border-amber-200 bg-amber-50 text-amber-800'],
                        ['processing', 'Provider đang xử lý', 'border-blue-200 bg-blue-50 text-blue-800'],
                        ['completed', 'Đơn hoàn tất', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
                        ['failed', 'Thất bại, xem failure_reason', 'border-rose-200 bg-rose-50 text-rose-800'],
                    ] as [$status, $description, $classes])
                        <div class="rounded-[6px] border p-3 {{ $classes }}"><code class="font-black">{{ $status }}</code><p class="mt-1 text-xs leading-5">{{ $description }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="errors" class="overflow-hidden rounded-[8px] border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6"><h2 class="text-xl font-extrabold text-slate-950">Lỗi và HTTP status</h2><p class="mt-1 text-sm leading-6 text-slate-600">Luôn kiểm tra cả HTTP status và trường JSON <code>status</code> trước khi xử lý dữ liệu.</p></div>
            <div class="grid gap-5 p-5 sm:p-6">
                <div class="overflow-x-auto rounded-[6px] border border-slate-200">
                    <table class="w-full min-w-[42rem] text-left text-sm"><thead class="bg-slate-100 text-xs font-bold uppercase tracking-wide text-slate-600"><tr><th class="px-4 py-3">HTTP</th><th class="px-4 py-3">Ý nghĩa</th><th class="px-4 py-3">Cách xử lý</th></tr></thead><tbody class="divide-y divide-slate-200">
                        @foreach ([
                            ['200', 'Request thành công hoặc request_id đã tồn tại', 'Đọc data và đồng bộ trạng thái.'],
                            ['201', 'Tạo đơn mới thành công', 'Lưu order_id để tra cứu.'],
                            ['401', 'Sai, hết hạn hoặc đã thu hồi key/secret', 'Kiểm tra header hoặc tạo key mới.'],
                            ['403', 'Thiếu quyền, IP không hợp lệ hoặc tài khoản bị khóa', 'Kiểm tra permission, whitelist và trạng thái tài khoản.'],
                            ['404', 'Không tìm thấy đơn thuộc tài khoản', 'Kiểm tra lại order_id.'],
                            ['409', 'request_id đã được tài khoản khác sử dụng', 'Tạo một UUID mới.'],
                            ['422', 'Payload, số dư hoặc dữ liệu game không hợp lệ', 'Hiển thị message và sửa request.'],
                            ['429', 'Vượt giới hạn request', 'Chờ rồi thử lại; không retry liên tục.'],
                        ] as [$status, $meaning, $handling])
                            <tr><td class="px-4 py-3"><code class="font-black text-slate-950">{{ $status }}</code></td><td class="px-4 py-3 text-slate-700">{{ $meaning }}</td><td class="px-4 py-3 text-slate-600">{{ $handling }}</td></tr>
                        @endforeach
                    </tbody></table>
                </div>
                <div class="grid gap-4 lg:grid-cols-2">
                    <x-client.api-code-block title="Response 401" :code="$authenticationError" copyable />
                    <x-client.api-code-block title="Response 422" :code="$validationError" copyable />
                </div>
            </div>
        </section>
    </main>

    <aside class="h-fit rounded-[8px] border border-slate-200 bg-slate-50 p-4 shadow-sm xl:sticky xl:top-24">
        <h2 class="text-sm font-extrabold text-slate-950">Mục lục tài liệu</h2>
        <nav class="mt-3 grid gap-1 text-sm" aria-label="Mục lục tài liệu API">
            @foreach ([['authentication', 'Xác thực'], ['balance', 'Kiểm tra số dư'], ['catalog', 'Danh mục và giá'], ['create-order', 'Tạo đơn'], ['order-status', 'Trạng thái đơn'], ['errors', 'Lỗi và HTTP status']] as [$anchor, $label])
                <a class="rounded-[5px] px-3 py-2 font-semibold text-slate-600 transition hover:bg-white hover:text-cyan-700" href="#{{ $anchor }}">{{ $label }}</a>
            @endforeach
        </nav>
        <a class="client-button mt-4 min-h-11 w-full justify-center" href="{{ route('account.profile.api') }}"><i class="bx bx-key text-lg" aria-hidden="true"></i>Quản lý API key</a>
        <div class="mt-4 grid gap-2 border-t border-slate-200 pt-4 text-xs leading-5 text-slate-600">
            <p><strong>GET:</strong> tối đa 60 request/phút.</p>
            <p><strong>Tạo đơn:</strong> tối đa 10 request/phút.</p>
            <p><strong>Bảo mật:</strong> chỉ gọi từ backend qua HTTPS.</p>
        </div>
    </aside>
</div>
