@props(['documentation'])

@php
    $balanceEndpoint = $documentation['balance_endpoint'];
    $createTaskEndpoint = $documentation['create_task_endpoint'];
    $taskStatusEndpoint = $documentation['task_status_endpoint'];
    $maxRecipients = $documentation['max_recipients'];
    $maxQuantity = $documentation['max_quantity_per_recipient'];

    $createRequest = json_encode([
        'request_id' => '550e8400-e29b-41d4-a716-446655440000',
        'game_id' => 1,
        'server_id' => 2,
        'package_id' => 3,
        'recipients' => [
            ['data' => ['game_account' => 'player-one', 'game_character' => 'Hero One'], 'quantity' => 6],
            ['data' => ['game_account' => 'player-two', 'game_character' => 'Hero Two'], 'quantity' => 5],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $balanceCurl = "curl --request GET '{$balanceEndpoint}' \\\n  --header 'Accept: application/json' \\\n  --header 'X-API-KEY: YOUR_API_KEY' \\\n  --header 'X-API-SECRET: YOUR_API_SECRET'";
    $createTaskCurl = "curl --request POST '{$createTaskEndpoint}' \\\n  --header 'Accept: application/json' \\\n  --header 'Content-Type: application/json' \\\n  --header 'X-API-KEY: YOUR_API_KEY' \\\n  --header 'X-API-SECRET: YOUR_API_SECRET' \\\n  --data '{$createRequest}'";
    $taskStatusCurl = "curl --request GET '{$taskStatusEndpoint}' \\\n  --header 'Accept: application/json' \\\n  --header 'X-API-KEY: YOUR_API_KEY' \\\n  --header 'X-API-SECRET: YOUR_API_SECRET'";
    $taskResponse = json_encode([
        'status' => true,
        'data' => [
            'task_id' => 'TOP260823ABCDEF',
            'request_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => 'pending',
            'payment_status' => 'paid',
            'game' => ['id' => 1, 'name' => 'Ngọc Rồng Online'],
            'server' => ['id' => 2, 'name' => 'Vũ trụ 2'],
            'package' => ['id' => 3, 'name' => 'Gói 10.000đ'],
            'quantity' => 11,
            'amount' => 93500,
            'currency' => 'VND',
            'recipients' => [
                [
                    'position' => 1,
                    'data' => ['game_account' => 'player-one', 'game_character' => 'Hero One'],
                    'quantity' => 6,
                    'status' => 'pending',
                    'failure_reason' => null,
                    'completed_at' => null,
                    'failed_at' => null,
                ],
                [
                    'position' => 2,
                    'data' => ['game_account' => 'player-two', 'game_character' => 'Hero Two'],
                    'quantity' => 5,
                    'status' => 'pending',
                    'failure_reason' => null,
                    'completed_at' => null,
                    'failed_at' => null,
                ],
            ],
            'failure_reason' => null,
            'created_at' => '2026-08-23T10:00:00+07:00',
            'updated_at' => '2026-08-23T10:00:00+07:00',
            'processing_at' => null,
            'completed_at' => null,
            'failed_at' => null,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="grid min-w-0 content-start gap-4">
        <header class="rounded-[5px] border border-slate-200 bg-slate-950 p-5 text-white sm:p-6">
            <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.16em] text-cyan-300"><i class="bx bx-code-curly text-lg" aria-hidden="true"></i>REST API v1</p>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight sm:text-3xl">Tài liệu API nạp game</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Ba endpoint cần thiết để kiểm tra số dư, tạo task nạp game và theo dõi trạng thái xử lý.</p>
            <div class="mt-4 rounded-[5px] border border-white/10 bg-white/5 px-4 py-3">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Base URL</span>
                <code class="mt-1 block break-all text-sm font-bold text-cyan-200">{{ $documentation['base_url'] }}</code>
            </div>
        </header>

        <section id="authentication" class="rounded-[5px] border border-slate-200 bg-white p-4 sm:p-5">
            <h2 class="flex items-center gap-2 text-lg font-extrabold text-slate-950"><i class="bx bx-lock-alt text-xl text-emerald-700" aria-hidden="true"></i>Xác thực</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">Gửi cả hai header trong mọi request. API không sử dụng Bearer token.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <div class="rounded-[5px] border border-slate-200 bg-slate-50 p-3"><code class="font-bold text-indigo-700">X-API-KEY</code><p class="mt-1 text-xs leading-5 text-slate-500">Khóa định danh hiển thị trong trang quản lý API key.</p></div>
                <div class="rounded-[5px] border border-slate-200 bg-slate-50 p-3"><code class="font-bold text-indigo-700">X-API-SECRET</code><p class="mt-1 text-xs leading-5 text-slate-500">Secret chỉ hiển thị một lần khi tạo key.</p></div>
            </div>
        </section>

        <section id="balance" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/balance</code></div>
                <h2 class="mt-3 text-lg font-extrabold text-slate-950">Kiểm tra số dư</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Trả về số dư ví hiện tại dùng để tạo task.</p>
            </div>
            <div class="grid gap-4 p-4 sm:p-5 lg:grid-cols-2">
                <x-client.api-code-block title="cURL" :code="$balanceCurl" copyable />
                <x-client.api-code-block title="Response 200" code='{
  "status": true,
  "data": {
    "balance": 200000,
    "currency": "VND"
  }
}' />
            </div>
        </section>

        <section id="create-task" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-indigo-100 px-2.5 py-1 text-xs font-black text-indigo-700">POST</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/tasks</code></div>
                <h2 class="mt-3 text-lg font-extrabold text-slate-950">Tạo task nạp game</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Task thanh toán bằng số dư ví. Giá và tổng tiền luôn được tính lại phía server.</p>
            </div>

            <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 sm:grid-cols-3 sm:p-5">
                <div class="rounded-[5px] border border-slate-200 bg-white p-3"><strong class="text-lg text-slate-950">{{ $maxRecipients }}</strong><p class="mt-1 text-xs text-slate-500">tài khoản tối đa trong một task</p></div>
                <div class="rounded-[5px] border border-slate-200 bg-white p-3"><strong class="text-lg text-slate-950">{{ $maxQuantity }}</strong><p class="mt-1 text-xs text-slate-500">thẻ tối đa cho mỗi tài khoản</p></div>
                <div class="rounded-[5px] border border-emerald-200 bg-emerald-50 p-3"><strong class="text-sm text-emerald-800">Tổng có thể &gt; {{ $maxQuantity }}</strong><p class="mt-1 text-xs text-emerald-700">Ví dụ 6 + 5 = 11 thẻ vẫn hợp lệ.</p></div>
            </div>

            <div class="grid gap-4 p-4 sm:p-5">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2.5">Field</th><th class="px-3 py-2.5">Kiểu</th><th class="px-3 py-2.5">Bắt buộc</th><th class="px-3 py-2.5">Mô tả</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr><td class="px-3 py-3 font-mono font-bold">request_id</td><td class="px-3 py-3">UUID</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">Khóa idempotency duy nhất cho task. Gửi lại cùng UUID sẽ nhận lại task cũ.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">game_id</td><td class="px-3 py-3">integer</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">ID game đang hoạt động.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">server_id</td><td class="px-3 py-3">integer</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">ID máy chủ thuộc game.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">package_id</td><td class="px-3 py-3">integer</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">ID gói nạp đang hoạt động.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">recipients</td><td class="px-3 py-3">array</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">Từ 1 đến {{ $maxRecipients }} tài khoản nhận.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">recipients.*.data</td><td class="px-3 py-3">object</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">Các field tài khoản đúng schema của game, ví dụ game_account, game_character.</td></tr>
                            <tr><td class="px-3 py-3 font-mono font-bold">recipients.*.quantity</td><td class="px-3 py-3">integer</td><td class="px-3 py-3">Có</td><td class="px-3 py-3 text-slate-600">Từ 1 đến {{ $maxQuantity }} thẻ cho riêng tài khoản đó.</td></tr>
                        </tbody>
                    </table>
                </div>
                <x-client.api-code-block title="Request JSON" :code="$createRequest" />
                <x-client.api-code-block title="cURL" :code="$createTaskCurl" copyable />
                <x-client.api-code-block title="Response 201" :code="$taskResponse" />
            </div>
        </section>

        <section id="task-status" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-2"><span class="rounded-[5px] bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-700">GET</span><code class="break-all text-sm font-bold text-slate-900">/api/v1/tasks/{task_id}</code></div>
                <h2 class="mt-3 text-lg font-extrabold text-slate-950">Lấy trạng thái task</h2>
                <p class="mt-1 text-sm leading-6 text-slate-600">Thay <code>TASK_ID</code> bằng <code>task_id</code> nhận được khi tạo task. Response có cùng schema task phía trên.</p>
            </div>
            <div class="grid gap-4 p-4 sm:p-5">
                <x-client.api-code-block title="cURL" :code="$taskStatusCurl" copyable />
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ([['pending', 'Chờ xử lý', 'bg-amber-50 text-amber-700'], ['processing', 'Đang xử lý', 'bg-blue-50 text-blue-700'], ['completed', 'Hoàn thành', 'bg-emerald-50 text-emerald-700'], ['failed', 'Thất bại', 'bg-rose-50 text-rose-700']] as [$status, $label, $class])
                        <div class="rounded-[5px] border border-slate-200 p-3"><code class="rounded-[5px] px-2 py-1 text-xs font-bold {{ $class }}">{{ $status }}</code><p class="mt-2 text-xs text-slate-500">{{ $label }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="errors" class="overflow-hidden rounded-[5px] border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4 sm:p-5"><h2 class="text-lg font-extrabold text-slate-950">HTTP status và lỗi</h2><p class="mt-1 text-sm text-slate-600">Lỗi luôn trả JSON ngắn gọn với <code>status: false</code> và <code>message</code>.</p></div>
            <div class="overflow-x-auto p-4 sm:p-5">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-3 py-2.5">HTTP</th><th class="px-3 py-2.5">Trường hợp</th><th class="px-3 py-2.5">Cách xử lý</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ([[200, 'Thành công hoặc request_id đã tồn tại', 'Dùng data trả về'], [201, 'Tạo task mới thành công', 'Lưu task_id để theo dõi'], [401, 'Sai hoặc thiếu API key/secret', 'Kiểm tra hai header xác thực'], [403, 'Thiếu quyền hoặc tài khoản bị khóa', 'Kiểm tra trạng thái key và tài khoản'], [404, 'Không tìm thấy task', 'Kiểm tra task_id và chủ sở hữu'], [409, 'request_id xung đột', 'Tạo UUID mới cho task mới'], [422, 'Payload sai hoặc số dư không đủ', 'Đọc message và sửa request'], [429, 'Vượt rate limit', 'Chờ rồi gửi lại']] as [$status, $case, $resolution])
                            <tr><td class="px-3 py-3 font-mono font-black text-slate-900">{{ $status }}</td><td class="px-3 py-3 text-slate-700">{{ $case }}</td><td class="px-3 py-3 text-slate-500">{{ $resolution }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="h-fit rounded-[5px] border border-slate-200 bg-slate-50 p-4 xl:sticky xl:top-24">
        <h2 class="text-sm font-extrabold text-slate-950">Trong trang này</h2>
        <nav class="mt-3 grid gap-1 text-sm" aria-label="Mục lục tài liệu API">
            @foreach ([['authentication', 'Xác thực'], ['balance', 'Kiểm tra số dư'], ['create-task', 'Tạo task'], ['task-status', 'Lấy trạng thái'], ['errors', 'HTTP status']] as [$anchor, $label])
                <a class="rounded-[5px] px-3 py-2 font-semibold text-slate-600 transition hover:bg-white hover:text-emerald-700" href="#{{ $anchor }}">{{ $label }}</a>
            @endforeach
        </nav>
        <a class="client-button mt-4 min-h-11 w-full justify-center" href="{{ route('account.profile.api') }}"><i class="bx bx-key text-lg" aria-hidden="true"></i>Quản lý API key</a>
        <p class="mt-3 text-xs leading-5 text-slate-500">Endpoint tạo task: 10 request/phút. Endpoint số dư và trạng thái: 60 request/phút.</p>
    </aside>
</div>
