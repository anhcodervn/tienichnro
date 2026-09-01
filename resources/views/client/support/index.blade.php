@extends('client.layouts.app')

@section('title', 'Chat hỗ trợ')
@section('description', 'Trò chuyện trực tiếp với đội ngũ hỗ trợ Nạp Carot.')
@section('robots', 'noindex,nofollow')

@push('head')
    @vite('resources/js/client-support.js')
@endpush

@section('content')
    <section
        class="client-container py-6 sm:py-8 lg:py-10"
        data-support-chat
        data-thread-url="{{ route('client.support.index') }}"
        data-send-url="{{ route('client.support.messages.store') }}"
        data-read-url="{{ route('client.support.read') }}"
        data-user-id="{{ auth()->id() }}"
        data-channel="users.{{ auth()->id() }}.support"
    >
        <div class="mb-5 flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <a class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-emerald-700" href="{{ route('home') }}">
                    <i class="bx bx-arrow-left text-lg" aria-hidden="true"></i>
                    Trang chủ
                </a>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Chat trực tiếp với hỗ trợ</h1>
                <p class="mt-1 text-sm leading-6 text-slate-600">Tin nhắn được đồng bộ realtime với nhân viên quản trị.</p>
            </div>
            <div class="inline-flex w-fit items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm" data-support-connection>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-400 motion-safe:animate-pulse" data-support-connection-dot aria-hidden="true"></span>
                <span data-support-connection-text>Đang kết nối...</span>
            </div>
        </div>

        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="flex min-h-[36rem] min-w-0 flex-col overflow-hidden rounded-[12px] border border-slate-200 bg-white shadow-[0_18px_50px_-32px_rgba(15,23,42,0.4)]">
                <div class="flex items-center gap-3 border-b border-slate-200 bg-slate-50/80 px-4 py-3 sm:px-5">
                    <span class="relative grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-600 text-xl text-white shadow-sm">
                        <i class="bx bx-headphone-mic" aria-hidden="true"></i>
                        <span class="absolute bottom-0 right-0 h-3 w-3 rounded-full border-2 border-white bg-emerald-400" aria-hidden="true"></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-extrabold text-slate-950">Đội ngũ hỗ trợ Nạp Carot</p>
                        <p class="truncate text-xs text-slate-500">Hãy mô tả rõ vấn đề để được hỗ trợ nhanh hơn</p>
                    </div>
                    <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-[8px] border border-slate-200 bg-white text-slate-500 transition hover:border-emerald-300 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600" data-support-refresh aria-label="Tải lại tin nhắn">
                        <i class="bx bx-refresh-cw text-xl" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="relative min-h-0 flex-1 bg-slate-50/60">
                    <div class="absolute inset-0 overflow-y-auto overscroll-contain px-3 py-4 sm:px-5" data-support-message-area aria-live="polite" aria-busy="true">
                        <div class="flex justify-center pb-3">
                            <button type="button" class="hidden min-h-9 items-center gap-2 rounded-full border border-slate-200 bg-white px-3 text-xs font-bold text-slate-600 shadow-sm transition hover:border-emerald-300 hover:text-emerald-700 disabled:cursor-wait disabled:opacity-60" data-support-load-older>
                                <i class="bx bx-history text-base" aria-hidden="true"></i>
                                <span data-support-load-older-text>Tải tin nhắn cũ</span>
                            </button>
                        </div>

                        <div class="grid gap-3" data-support-messages></div>

                        <div class="grid min-h-52 place-items-center px-6 text-center" data-support-empty hidden>
                            <div>
                                <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700"><i class="bx bx-message-circle-dots" aria-hidden="true"></i></span>
                                <h2 class="mt-4 font-extrabold text-slate-900">Bắt đầu cuộc trò chuyện</h2>
                                <p class="mt-1 max-w-sm text-sm leading-6 text-slate-500">Gửi câu hỏi đầu tiên, nhân viên hỗ trợ sẽ nhận được ngay trong trang quản trị.</p>
                            </div>
                        </div>

                        <div class="grid min-h-52 place-items-center px-6 text-center" data-support-loading>
                            <div>
                                <i class="bx bx-loader-lines animate-spin text-3xl text-emerald-600" aria-hidden="true"></i>
                                <p class="mt-2 text-sm font-semibold text-slate-500">Đang tải cuộc trò chuyện...</p>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="absolute bottom-3 left-1/2 hidden -translate-x-1/2 items-center gap-2 rounded-full bg-slate-950 px-4 py-2 text-xs font-bold text-white shadow-lg transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2" data-support-new-message>
                        <i class="bx bx-arrow-down text-base" aria-hidden="true"></i>
                        Tin nhắn mới
                    </button>
                </div>

                <div class="border-t border-slate-200 bg-white p-3 sm:p-4">
                    <p class="mb-2 hidden rounded-[8px] border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700" data-support-error role="alert"></p>
                    <form class="flex min-w-0 items-end gap-2" data-support-form>
                        <label class="sr-only" for="support-message">Nội dung tin nhắn</label>
                        <textarea
                            id="support-message"
                            class="max-h-36 min-h-12 min-w-0 flex-1 resize-none rounded-[10px] border-slate-300 px-3.5 py-3 text-sm leading-6 text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500"
                            rows="1"
                            maxlength="5000"
                            placeholder="Nhập tin nhắn..."
                            data-support-input
                        ></textarea>
                        <button type="submit" class="inline-flex h-12 min-w-[6.75rem] shrink-0 items-center justify-center gap-2 rounded-[10px] bg-emerald-600 px-3 text-sm font-extrabold text-white shadow-sm transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-400 disabled:opacity-90" data-support-send aria-label="Gửi tin nhắn">
                            <i class="bx bx-send text-lg" data-support-send-icon aria-hidden="true"></i>
                            <span data-support-send-label>Gửi</span>
                        </button>
                    </form>
                    <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-400">
                        <span>Tối đa 1 tin/10 giây · 6 tin/phút</span>
                        <span><span data-support-character-count>0</span>/5000</span>
                    </div>
                </div>
            </div>

            <aside class="grid content-start gap-3">
                <div class="rounded-[12px] border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-start gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[9px] bg-emerald-600 text-xl text-white"><i class="bx bx-bolt-circle" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="font-extrabold text-emerald-950">Hỗ trợ realtime</h2>
                            <p class="mt-1 text-sm leading-6 text-emerald-800">Phản hồi từ admin sẽ xuất hiện ngay tại đây khi bạn đang mở trang.</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-[12px] border border-slate-200 bg-white p-4 shadow-sm">
                    <h2 class="flex items-center gap-2 font-extrabold text-slate-900"><i class="bx bx-info-circle text-xl text-slate-500" aria-hidden="true"></i>Khi cần hỗ trợ</h2>
                    <ul class="mt-3 grid gap-3 text-sm leading-6 text-slate-600">
                        <li class="flex gap-2"><i class="bx bx-check-circle mt-1 text-emerald-600" aria-hidden="true"></i><span>Gửi mã đơn hoặc mã giao dịch nếu có.</span></li>
                        <li class="flex gap-2"><i class="bx bx-check-circle mt-1 text-emerald-600" aria-hidden="true"></i><span>Không gửi mật khẩu hay mã xác thực.</span></li>
                        <li class="flex gap-2"><i class="bx bx-check-circle mt-1 text-emerald-600" aria-hidden="true"></i><span>Giữ trang mở để nhận phản hồi realtime.</span></li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>
@endsection
