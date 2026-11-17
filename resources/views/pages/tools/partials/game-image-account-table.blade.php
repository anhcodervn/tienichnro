                    <div class="min-w-0 overflow-x-auto overscroll-x-contain">
                    <table class="w-full min-w-[56rem] text-left text-sm" data-game-image-account-table>
                        <caption class="sr-only">Danh sách tài khoản ghép ảnh của bạn</caption>
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th scope="col" class="w-12 px-4 py-3">#</th><th scope="col" class="px-4 py-3">Username</th><th scope="col" class="px-4 py-3">Ảnh preview</th><th scope="col" class="px-4 py-3">Trạng thái</th><th scope="col" class="px-4 py-3">Quá trình chụp</th><th scope="col" class="px-4 py-3">Thao tác</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @forelse($accounts as $account)
                            @php($maskedUsername = mb_strlen($account->username) > 6 ? mb_substr($account->username, 0, 3).'***'.mb_substr($account->username, -3) : mb_substr($account->username, 0, 1).'***'.mb_substr($account->username, -1))
                            <tr class="align-top transition hover:bg-slate-50/60">
                                <td class="px-4 py-4 tabular-nums text-slate-400">{{ $accounts->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-4"><div class="flex items-center gap-2" data-game-image-username data-username="{{ $account->username }}" data-masked-username="{{ $maskedUsername }}"><span class="break-all font-semibold text-slate-900" data-game-image-username-value>{{ $maskedUsername }}</span><button type="button" data-game-image-username-toggle aria-label="Hiện tên tài khoản" aria-pressed="false" class="grid h-8 w-8 shrink-0 place-items-center rounded-[10px] text-lg text-slate-500 hover:bg-slate-100 hover:text-emerald-700"><i class="bx bx-eye" aria-hidden="true"></i></button></div><p class="mt-1 break-words text-xs text-slate-500">@if($account->status === 'success' && filled($account->cname ?? $account->layout_cname)){{ $account->cname ?? $account->layout_cname }} &#8226; @endif{{ $account->server }}</p></td>
                                <td class="px-4 py-4">
                                    @if($account->preview_path)
                                        <img src="{{ route('tools.game-image.preview', ['account' => $account, 'v' => $account->updated_at->timestamp]) }}" alt="Ảnh ghép tài khoản #{{ $account->id }}" loading="lazy" tabindex="0" role="button" aria-label="Xem ảnh lớn" class="h-20 w-32 cursor-zoom-in rounded-[10px] border border-slate-200 bg-slate-50 object-contain">
                                        <a class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:underline" href="{{ route('tools.game-image.preview', ['account' => $account, 'download' => 1]) }}"><i class="bx bx-download" aria-hidden="true"></i>Tải ảnh</a>
                                    @else
                                        <div class="grid h-20 w-32 place-items-center rounded-[10px] border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400">Chưa lưu ảnh ghép</div>
                                    @endif
                                </td>
                                <td class="max-w-64 px-4 py-4"><span @class(['inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700' => $account->status === 'success', 'bg-sky-50 text-sky-700' => $account->status === 'processing', 'bg-amber-50 text-amber-700' => $account->status === 'pending', 'bg-rose-50 text-rose-700' => $account->status === 'error'])>{{ ['pending' => 'Chờ ghép', 'processing' => 'Đang chụp ảnh', 'success' => 'Hoàn tất', 'error' => 'Lỗi'][$account->status] }}</span>
                                    @if($account->status === 'error')
                                        @if($account->error_code)<p class="mt-2 break-all font-mono text-xs font-semibold text-rose-700">{{ $account->error_code }}</p>@endif
                                        <p class="mt-2 break-words whitespace-pre-line text-xs leading-5 text-rose-600">{{ $account->error_message }}</p>@endif
                                    @if($account->refunded_at)<p class="mt-1 text-xs font-semibold text-emerald-700">Đã hoàn {{ number_format($account->charged_amount, 0, ',', '.') }} đ</p>@endif
                                </td>
                                <td class="px-4 py-4">
                                    @php($logText = $account->captureLogs->where('capture_version', $account->capture_version)->reverse()->map(fn ($log) => '['.($log->occurred_at ?? $log->created_at)->format('H:i:s').'] '.$log->message)->implode("\n"))
                                    <textarea readonly rows="5" aria-label="Quá trình chụp tài khoản #{{ $account->id }}" data-game-image-capture-log="{{ $account->id }}:{{ $account->capture_version }}" placeholder="Chưa có log" class="block w-72 max-w-[75vw] resize-none overflow-y-auto overscroll-y-contain rounded-[10px] border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs leading-5 text-slate-700">{{ $logText }}</textarea>
                                    <button type="button" data-game-image-view-log="{{ $account->id }}:{{ $account->capture_version }}" class="client-button-secondary mt-2 !min-h-8 !px-3 !text-xs">Xem log</button>
                                </td>
                                <td class="px-4 py-4"><div class="grid justify-items-start gap-2"><a class="client-button-secondary !min-h-9 !px-3 !py-1.5 !text-xs" href="{{ route('tools.game-image.edit', $account) }}"><i class="bx bx-edit mr-1" aria-hidden="true"></i>Điều chỉnh</a>
                                    <button type="button" class="client-button-secondary !min-h-9 !px-3 !py-1.5 !text-xs disabled:cursor-not-allowed disabled:opacity-50" data-game-image-recapture data-url="{{ route('tools.game-image.recapture', $account) }}" data-price="{{ $capturePrice }}" data-version="{{ $account->capture_version }}" @disabled($capturePrice === null || in_array($account->status, ['pending', 'processing'], true))><i class="bx bx-camera mr-1" aria-hidden="true"></i>Chụp lại</button>
                                    @if($account->capture_directory)<a class="inline-flex min-h-9 items-center gap-1 rounded-[10px] border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50" href="{{ route('tools.game-image.download-data', $account) }}"><i class="bx bx-download" aria-hidden="true"></i>Tải dữ liệu</a>@else<button type="button" disabled title="Tool chưa tải lên bộ ảnh của tài khoản" class="inline-flex min-h-9 items-center gap-1 rounded-[10px] border border-slate-200 px-3 py-1.5 text-xs text-slate-400 disabled:cursor-not-allowed"><i class="bx bx-download" aria-hidden="true"></i>Tải dữ liệu</button>@endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">{{ filled($filters['q'] ?? null) || filled($filters['status'] ?? null) ? 'Không có tài khoản phù hợp với bộ lọc.' : 'Chưa có tài khoản. Thêm tài khoản đầu tiên để bắt đầu ghép ảnh.' }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                    </div>
                    <footer class="grid gap-3 border-t border-slate-100 p-4"><p class="text-xs text-slate-500">Hiển thị {{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }} / {{ $accounts->total() }} tài khoản · 20 tài khoản/trang</p>{{ $accounts->links() }}</footer>
