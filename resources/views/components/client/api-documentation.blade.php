@props(['documentation'])

@php
    $balanceEndpoint = $documentation['balance_endpoint'];
    $catalogEndpoint = $documentation['catalog_endpoint'];
    $createOrderEndpoint = $documentation['create_order_endpoint'];
    $orderStatusEndpoint = $documentation['order_status_endpoint'];
    $maxRecipients = $documentation['max_recipients'];
    $maxQuantity = $documentation['max_quantity_per_recipient'];
    $headers = "--header 'Accept: application/json' \\\n+  --header 'X-API-KEY: YOUR_API_KEY' \\\n+  --header 'X-API-SECRET: YOUR_API_SECRET'";
    $createRequest = json_encode([
        'request_id' => '550e8400-e29b-41d4-a716-446655440000',
        'game' => 1,
        'server' => 2,
        'price' => 10000,
        'payload' => [
            ['account' => 'player-one', 'character' => 'Hero One', 'amount' => 2],
            ['account' => 'player-two', 'character' => 'Hero Two', 'amount' => 1],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $balanceCurl = "curl --request GET '{$balanceEndpoint}' \\\n+  {$headers}";
    $catalogCurl = "curl --request GET '{$catalogEndpoint}' \\\n+  {$headers}";
    $createOrderCurl = "curl --request POST '{$createOrderEndpoint}' \\\n+  {$headers} \\\n+  --header 'Content-Type: application/json' \\\n+  --data '{$createRequest}'";
    $orderStatusCurl = "curl --request GET '{$orderStatusEndpoint}' \\\n+  {$headers}";
    $orderResponse = json_encode([
        'status' => true,
        'data' => [
            'order_id' => 'TOP260823ABCDEF',
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
            'created_at' => '2026-08-23T10:00:00+07:00',
            'updated_at' => '2026-08-23T10:00:00+07:00',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="grid min-w-0 content-start gap-4">
        <header class="rounded-[5px] border border-slate-200 bg-slate-950 p-5 text-white sm:p-6">
            <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-300"><i class="bx bx-code-curly text-lg" aria-hidden="true"></i>REST API v1</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Tài liệu API nạp game</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Ba nhóm API: số dư, danh mục và đơn nạp. Nhóm đơn hỗ trợ cả tạo đơn lẫn kiểm tra trạng thái.</p>
            <div class="mt-4 rounded-[5px] border border-white/10 bg-white/5 px-4 py-3"><span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Base URL</span><code class="mt-1 block break-all text-sm font-bold text-cyan-200">{{ $documentation['base_url'] }}</code></div>
        </header>

        <section id="authentication" class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-extrabold text-slate-950"><i class="bx bx-lock-alt text-xl text-emerald-700" aria-hidden="true"></i>Xác thực</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Gửi <code>X-API-KEY</code> và <code>X-API-SECRET</code> trong mọi request. API không dùng Bearer token.</p>
        </section>

        <section id="balance" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5"><div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/balance</code></div><h2 class="mt-3 text-lg font-extrabold text-slate-950">Kiểm tra số dư</h2></div>
            <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-2"><x-client.api-code-block title="cURL" :code="$balanceCurl" copyable /><x-client.api-code-block title="Response 200" code='{"status":true,"data":{"balance":200000,"currency":"VND"}}' /></div>
        </section>

        <section id="catalog" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5"><div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/catalog</code></div><h2 class="mt-3 text-lg font-extrabold text-slate-950">Danh mục game, server và gói nạp</h2><p class="mt-1 text-sm leading-6 text-slate-600">Dùng ID, mệnh giá, giá bán và <code>payload_fields</code> để dựng form. Response không chứa thông tin provider.</p></div>
            <div class="p-4 sm:p-5"><x-client.api-code-block title="cURL" :code="$catalogCurl" copyable /></div>
        </section>

        <section id="create-order" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5"><div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-indigo-100 px-2.5 py-1 text-xs font-black text-indigo-700">POST</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/orders</code></div><h2 class="mt-3 text-lg font-extrabold text-slate-950">Tạo đơn nạp</h2><p class="mt-1 text-sm leading-6 text-slate-600"><code>price</code> là mệnh giá; backend tự chọn gói và tính giá bán. <code>payload</code> nhận tối đa {{ $maxRecipients }} tài khoản, mỗi tài khoản có <code>amount</code> từ 1 đến {{ $maxQuantity }}.</p></div>
            <div class="grid gap-4 p-4 sm:p-5">
                <div class="overflow-x-auto"><table class="w-full min-w-[46rem] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2.5">Field</th><th class="px-3 py-2.5">Kiểu</th><th class="px-3 py-2.5">Mô tả</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ([['request_id', 'UUID', 'Khóa chống tạo trùng đơn'], ['game', 'integer', 'ID game từ catalog'], ['server', 'integer', 'ID server từ catalog'], ['price', 'integer', 'Mệnh giá gói nạp'], ['payload', 'array', 'Danh sách dữ liệu nhận hàng'], ['payload.*.amount', 'integer', 'Số lượng riêng của từng tài khoản']] as [$field, $type, $description])<tr><td class="px-3 py-3 font-mono font-bold">{{ $field }}</td><td class="px-3 py-3">{{ $type }}</td><td class="px-3 py-3 text-slate-600">{{ $description }}</td></tr>@endforeach</tbody></table></div>
                <x-client.api-code-block title="Request JSON" :code="$createRequest" />
                <x-client.api-code-block title="cURL" :code="$createOrderCurl" copyable />
                <x-client.api-code-block title="Response 201" :code="$orderResponse" />
            </div>
        </section>

        <section id="order-status" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5"><div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/orders/{order_id}</code></div><h2 class="mt-3 text-lg font-extrabold text-slate-950">Kiểm tra trạng thái đơn</h2><p class="mt-1 text-sm leading-6 text-slate-600">Dùng <code>order_id</code> từ API tạo đơn. Trạng thái gồm pending, processing, completed và failed.</p></div>
            <div class="p-4 sm:p-5"><x-client.api-code-block title="cURL" :code="$orderStatusCurl" copyable /></div>
        </section>

        <section id="errors" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5"><h2 class="text-lg font-extrabold text-slate-950">HTTP status và lỗi</h2><p class="mt-2 text-sm leading-6 text-slate-600">200: thành công hoặc request_id đã tồn tại; 201: tạo mới; 401: sai key; 403: thiếu quyền; 404: không thấy đơn; 409: request_id xung đột; 422: dữ liệu hoặc số dư không hợp lệ; 429: vượt rate limit.</p></section>
    </div>

    <aside class="h-fit rounded-[5px] border border-slate-200 bg-slate-50 p-4 xl:sticky xl:top-24">
        <h2 class="text-sm font-extrabold text-slate-950">Trong trang này</h2>
        <nav class="mt-3 grid gap-1 text-sm" aria-label="Mục lục tài liệu API">@foreach ([['authentication', 'Xác thực'], ['balance', 'Kiểm tra số dư'], ['catalog', 'Danh mục'], ['create-order', 'Tạo đơn'], ['order-status', 'Lấy trạng thái'], ['errors', 'HTTP status']] as [$anchor, $label])<a class="rounded-[5px] px-3 py-2 font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-700" href="#{{ $anchor }}">{{ $label }}</a>@endforeach</nav>
        <a class="client-button mt-4 min-h-11 w-full justify-center" href="{{ route('account.profile.api') }}"><i class="bx bx-key text-lg" aria-hidden="true"></i>Quản lý API key</a>
        <p class="mt-3 text-xs leading-5 text-slate-500">Tạo đơn: 10 request/phút. Các endpoint GET: 60 request/phút.</p>
    </aside>
</div>
