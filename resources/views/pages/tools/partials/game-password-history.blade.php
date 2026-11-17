<div class="min-w-0 overflow-x-auto">
    <table class="w-full min-w-[64rem] text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Tài khoản / Server</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3">Tiến trình</th><th class="px-4 py-3">Log xử lý</th><th class="px-4 py-3">Phí</th><th class="px-4 py-3">Thời gian</th><th class="px-4 py-3">Thao tác</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse($orders ?? [] as $order)
            <tr class="align-top" data-password-order="{{ $order->id }}" data-password-status="{{ $order->status }}" @if(in_array($order->status, ['pending', 'processing'], true)) data-password-active @endif>
                <td class="px-4 py-4 text-slate-400">#{{ $order->id }}</td>
                <td class="px-4 py-4"><p class="font-semibold text-slate-900">{{ mb_substr($order->username, 0, 3) }}***{{ mb_substr($order->username, -3) }}</p><p class="mt-1 text-xs text-slate-500">Server {{ $order->server_code }}</p></td>
                <td class="px-4 py-4"><span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $order->status === 'success', 'bg-rose-50 text-rose-700' => $order->status === 'error', 'bg-amber-50 text-amber-700' => in_array($order->status, ['pending', 'needs_review'], true), 'bg-sky-50 text-sky-700' => $order->status === 'processing'])>{{ $statuses[$order->status] }}</span></td>
                <td class="max-w-80 px-4 py-4 text-xs leading-5 text-slate-600"><p>{{ $progressLabels[$order->progress_code] ?? App\Models\GamePasswordOrder::LOG_MESSAGES[$order->progress_code] ?? '' }}</p>@if($order->error_code)<p class="mt-1 break-all font-mono text-rose-600">{{ $order->error_code }}</p><p class="mt-1 text-rose-600">{{ App\Features\Client\GamePassword\Resources\GamePasswordOrderResource::errorMessage($order->error_code) }}</p>@endif</td>
                <td class="px-4 py-4">
                    <textarea readonly rows="5" aria-label="Log đổi mật khẩu #{{ $order->id }}" data-password-log="{{ $order->id }}" placeholder="Đơn cũ chưa có log chi tiết" class="block w-72 max-w-[75vw] resize-none overflow-y-auto overscroll-y-contain rounded-[10px] border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs leading-5 text-slate-700">{{ $order->progressLogText() }}</textarea>
                    <button type="button" data-password-view-log="{{ $order->id }}" aria-haspopup="dialog" aria-controls="password-log-modal" class="client-button-secondary mt-2 !min-h-8 !px-3 !text-xs">Xem log</button>
                </td>
                <td class="whitespace-nowrap px-4 py-4 text-xs">{{ number_format($order->charged_amount, 0, ',', '.') }} đ @if($order->refunded_at)<p class="mt-1 font-semibold text-emerald-700">Đã hoàn phí</p>@endif</td>
                <td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $order->created_at->format('d/m/Y H:i:s') }}</td>
                <td class="px-4 py-4">
                    @if($order->canRetry())
                        <button type="button" class="client-button-secondary !min-h-9 whitespace-nowrap !px-3 !text-xs disabled:cursor-not-allowed disabled:opacity-50" data-password-retry data-attempt-version="{{ $order->attempt_version }}" data-retry-url="{{ route('tools.game-password.retry', $order->id) }}" @disabled(!$order->hasSavedCredentials())>Đổi lại miễn phí</button>
                        @unless($order->hasSavedCredentials())<p class="mt-1 max-w-48 text-xs text-slate-500">Đơn cũ không còn mật khẩu đã lưu.</p>@endunless
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">Chưa có yêu cầu phù hợp.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($orders)<footer class="grid gap-3 border-t border-slate-100 p-4"><p class="text-xs text-slate-500">{{ $orders->total() }} yêu cầu</p>{{ $orders->links() }}</footer>@endif
