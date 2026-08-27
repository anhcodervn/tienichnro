<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import { ArrowDownToLine, Copy, LoaderCircle, PackageOpen, Send } from 'lucide-vue-next';
import { computed } from 'vue';
import type { ActionOption, OrderAction, OrderRow, ProviderExchange, ProviderItemRow, RecipientRow } from '../types';
import OrderStatusBadge from './OrderStatusBadge.vue';

const props = defineProps<{
    open: boolean;
    order: OrderRow | null;
    loading: boolean;
    error: string;
    primaryAction: ActionOption | null;
    acting: boolean;
}>();

const emit = defineEmits<{
    close: [];
    retry: [];
    action: [action: OrderAction];
    copy: [code: string];
}>();

const displayOrder = computed(() => props.order);

const formatMoney = (value: number | string): string => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
const formatDateTime = (value?: string | null): string =>
    value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';

const recipientData = (recipient: RecipientRow): string =>
    Object.entries(recipient.data || {})
        .filter(([, value]) => value !== null && value !== '')
        .map(([key, value]) => `${key}: ${String(value)}`)
        .join(' · ') || 'Không có dữ liệu người nhận';

const providerStep = (step?: string | null): { label: string; className: string } =>
    ({
        queued: { label: 'Chờ gửi provider', className: 'border-slate-200 bg-slate-100 text-slate-700' },
        awaiting_status: { label: 'Provider đang xử lý', className: 'border-amber-200 bg-amber-50 text-amber-800' },
        checking_status: { label: 'Đang tra cứu provider', className: 'border-sky-200 bg-sky-50 text-sky-800' },
        completed: { label: 'Provider đã hoàn thành', className: 'border-emerald-200 bg-emerald-50 text-emerald-800' },
        failed: { label: 'Provider báo thất bại', className: 'border-rose-200 bg-rose-50 text-rose-800' },
        manual_review: { label: 'Cần đối soát thủ công', className: 'border-violet-200 bg-violet-50 text-violet-800' },
    })[step || 'queued'] || { label: 'Chưa xác định', className: 'border-slate-200 bg-slate-100 text-slate-700' };

type ProviderEventView = ProviderExchange & { key: string; title: string; description: string };

const providerEvents = (item: ProviderItemRow): ProviderEventView[] => {
    const events: ProviderEventView[] = [];

    if (item.submission) {
        events.push({
            ...item.submission,
            key: 'submission',
            title: 'Tạo đơn provider',
            description: 'Request nạp ban đầu và response provider trả về.',
        });
    }

    if (item.last_status_check) {
        events.push({
            ...item.last_status_check,
            key: 'last-status-check',
            title: 'Tra cứu trạng thái gần nhất',
            description: `Lần kiểm tra #${item.last_status_check.attempt || item.check_attempts || 1}.`,
        });
    }

    return events;
};

const formatJson = (value: unknown): string => JSON.stringify(value ?? {}, null, 2);
</script>

<template>
    <Modal :model-value="open" panel-class="max-w-[1180px]" @update:model-value="!$event && emit('close')">
        <template #header>
            <header class="flex min-h-20 items-center border-b border-slate-100 px-5 pr-16 sm:px-6 sm:pr-16">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-indigo-600">Chi tiết đơn hàng</p>
                    <div class="mt-1 flex items-center gap-2">
                        <h2 class="truncate font-mono text-lg font-black text-slate-950">
                            {{ displayOrder?.code || 'Đang tải...' }}
                        </h2>
                        <button
                            v-if="displayOrder"
                            type="button"
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            aria-label="Sao chép mã đơn"
                            @click="emit('copy', displayOrder.code)"
                        >
                            <Copy class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </header>
        </template>

        <div class="px-5 py-5 sm:px-6">
            <div v-if="loading" class="grid min-h-72 place-items-center text-sm text-slate-500">
                <span class="inline-flex items-center gap-2"><LoaderCircle class="h-5 w-5 animate-spin" />Đang tải chi tiết...</span>
            </div>
            <div v-else-if="error" class="rounded-xl border border-rose-200 bg-rose-50 p-4" role="alert">
                <p class="font-semibold text-rose-800">Không tải được chi tiết đơn</p>
                <p class="mt-1 text-sm text-rose-700">{{ error }}</p>
                <button type="button" class="mt-3 rounded-lg bg-rose-700 px-3 py-2 text-sm font-semibold text-white" @click="emit('retry')">
                    Thử lại
                </button>
            </div>
            <div v-else-if="displayOrder" class="grid gap-5 lg:grid-cols-[minmax(280px,0.7fr)_minmax(0,1.3fr)]">
                <div class="space-y-5">
                    <section class="grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4">
                        <div>
                            <p class="text-xs font-medium text-slate-500">Thanh toán</p>
                            <OrderStatusBadge class="mt-2" kind="payment" :status="displayOrder.payment_status" />
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500">Trạng thái đơn</p>
                            <OrderStatusBadge class="mt-2" kind="order" :status="displayOrder.order_status" />
                        </div>
                        <div class="col-span-2 rounded-xl border border-indigo-100 bg-white px-3 py-2.5">
                            <p class="text-xs font-medium text-slate-500">Nhà cung cấp</p>
                            <div v-if="displayOrder.provider" class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="font-bold text-slate-900">{{ displayOrder.provider.name }}</span>
                                <span class="rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700">
                                    {{ displayOrder.provider.slug }}
                                </span>
                            </div>
                            <p v-else class="mt-1 text-sm text-slate-500">Xử lý thủ công / chưa gán provider</p>
                        </div>
                        <div class="col-span-2 border-t border-slate-200 pt-3">
                            <p class="text-xs font-medium text-slate-500">Tổng thanh toán</p>
                            <p class="mt-1 text-2xl font-black tabular-nums text-slate-950">{{ formatMoney(displayOrder.total_amount) }}</p>
                        </div>
                    </section>

                    <section>
                        <h3 class="text-sm font-bold text-slate-950">Thông tin đơn</h3>
                        <dl class="mt-3 grid grid-cols-[120px_1fr] gap-x-3 gap-y-3 text-sm">
                            <dt class="text-slate-500">Email</dt>
                            <dd class="break-all font-medium text-slate-800">{{ displayOrder.email }}</dd>
                            <dt class="text-slate-500">Game</dt>
                            <dd class="font-medium text-slate-800">{{ displayOrder.game || '—' }}</dd>
                            <dt class="text-slate-500">Máy chủ</dt>
                            <dd class="font-medium text-slate-800">{{ displayOrder.server || '—' }}</dd>
                            <dt class="text-slate-500">Tài khoản</dt>
                            <dd class="break-all font-mono font-semibold text-slate-800">{{ displayOrder.game_account || '—' }}</dd>
                            <dt class="text-slate-500">Gói nạp</dt>
                            <dd class="font-medium text-slate-800">{{ displayOrder.package_name }} × {{ displayOrder.quantity }}</dd>
                            <dt class="text-slate-500">Tạo lúc</dt>
                            <dd class="font-medium text-slate-800">{{ formatDateTime(displayOrder.created_at) }}</dd>
                            <dt class="text-slate-500">Thanh toán lúc</dt>
                            <dd class="font-medium text-slate-800">{{ formatDateTime(displayOrder.paid_at) }}</dd>
                            <dt class="text-slate-500">Mã provider</dt>
                            <dd class="break-all font-mono text-xs font-semibold text-slate-800">{{ displayOrder.provider_reference || '—' }}</dd>
                        </dl>
                    </section>

                    <section v-if="displayOrder.failure_reason" class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <h3 class="text-sm font-bold text-rose-800">Lý do lỗi</h3>
                        <p class="mt-1 text-sm leading-6 text-rose-700">{{ displayOrder.failure_reason }}</p>
                    </section>
                </div>

                <section class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-bold text-slate-950">Lượt nạp</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">{{
                            displayOrder.recipients?.length || 0
                        }}</span>
                    </div>
                    <div class="mt-3 space-y-3">
                        <article
                            v-for="recipient in displayOrder.recipients || []"
                            :key="recipient.position"
                            class="rounded-xl border border-slate-200 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Lượt #{{ recipient.position }} · SL {{ recipient.quantity }}
                                    </p>
                                    <p class="mt-1 break-words text-sm font-semibold text-slate-800">{{ recipientData(recipient) }}</p>
                                </div>
                                <OrderStatusBadge kind="order" :status="recipient.status" />
                            </div>
                            <p v-if="recipient.provider_reference" class="mt-2 break-all font-mono text-xs text-slate-500">
                                Ref: {{ recipient.provider_reference }}
                            </p>
                            <p v-if="recipient.failure_reason" class="mt-2 text-sm text-rose-700">{{ recipient.failure_reason }}</p>
                            <div v-if="recipient.provider_items?.length" class="mt-3 space-y-3 border-t border-slate-200 pt-3">
                                <section
                                    v-for="item in recipient.provider_items"
                                    :key="item.unit || 0"
                                    class="overflow-hidden rounded-xl border border-slate-200 bg-white"
                                >
                                    <div class="border-b border-slate-200 bg-slate-50 px-3 py-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div>
                                                <p class="text-sm font-black text-slate-900">Thẻ #{{ item.unit || 1 }}</p>
                                                <p class="mt-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Bước hiện tại</p>
                                            </div>
                                            <span
                                                class="rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                                :class="providerStep(item.current_step).className"
                                            >
                                                {{ providerStep(item.current_step).label }}
                                            </span>
                                        </div>
                                        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                                            <div class="min-w-0">
                                                <dt class="text-slate-500">Request ID</dt>
                                                <dd class="mt-0.5 break-all font-mono font-semibold text-slate-800">{{ item.request_id || '—' }}</dd>
                                            </div>
                                            <div class="min-w-0">
                                                <dt class="text-slate-500">Provider reference</dt>
                                                <dd class="mt-0.5 break-all font-mono font-semibold text-slate-800">{{ item.reference || '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-slate-500">Trạng thái chuẩn hóa</dt>
                                                <dd class="mt-0.5 font-semibold text-slate-800">{{ item.status || '—' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-slate-500">Lần tra cứu</dt>
                                                <dd class="mt-0.5 font-semibold text-slate-800">{{ item.check_attempts || 0 }}</dd>
                                            </div>
                                        </dl>
                                    </div>

                                    <div class="space-y-4 p-3">
                                        <article
                                            v-for="event in providerEvents(item)"
                                            :key="event.key"
                                            class="relative border-l-2 border-indigo-200 pl-4"
                                        >
                                            <span class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-indigo-500 ring-4 ring-white"></span>
                                            <div class="flex flex-wrap items-start justify-between gap-2">
                                                <div>
                                                    <h5 class="text-xs font-black text-slate-900">{{ event.title }}</h5>
                                                    <p class="mt-0.5 text-[11px] leading-5 text-slate-500">{{ event.description }}</p>
                                                </div>
                                                <time class="text-[11px] font-medium text-slate-500">{{ formatDateTime(event.recorded_at) }}</time>
                                            </div>

                                            <div class="mt-2 grid gap-2 xl:grid-cols-2">
                                                <section class="min-w-0 overflow-hidden rounded-lg border border-sky-200 bg-sky-50/60">
                                                    <header class="flex items-center justify-between gap-2 border-b border-sky-200 px-3 py-2">
                                                        <span
                                                            class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-sky-800"
                                                        >
                                                            <Send class="h-3.5 w-3.5" />Request gửi đi
                                                        </span>
                                                        <span class="font-mono text-[10px] font-bold text-sky-700">{{
                                                            event.request?.method || 'POST'
                                                        }}</span>
                                                    </header>
                                                    <p
                                                        class="break-all border-b border-sky-100 px-3 py-2 font-mono text-[10px] leading-4 text-sky-900"
                                                    >
                                                        {{ event.request?.url || '—' }}
                                                    </p>
                                                    <pre
                                                        class="max-h-64 overflow-auto whitespace-pre-wrap break-all px-3 py-2 font-mono text-[10px] leading-4 text-slate-700"
                                                        >{{ formatJson(event.request?.payload) }}</pre
                                                    >
                                                    <p class="border-t border-sky-100 px-3 py-1.5 text-[10px] font-medium text-sky-700">
                                                        Chữ ký và credential nhạy cảm đã được ẩn.
                                                    </p>
                                                </section>

                                                <section class="min-w-0 overflow-hidden rounded-lg border border-emerald-200 bg-emerald-50/60">
                                                    <header class="flex items-center justify-between gap-2 border-b border-emerald-200 px-3 py-2">
                                                        <span
                                                            class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wide text-emerald-800"
                                                        >
                                                            <ArrowDownToLine class="h-3.5 w-3.5" />Response trả về
                                                        </span>
                                                        <span class="font-mono text-[10px] font-bold text-emerald-700">
                                                            HTTP {{ event.response?.http_status || '—' }}
                                                        </span>
                                                    </header>
                                                    <pre
                                                        class="max-h-64 overflow-auto whitespace-pre-wrap break-all px-3 py-2 font-mono text-[10px] leading-4 text-slate-700"
                                                        >{{ formatJson(event.response?.body) }}</pre
                                                    >
                                                </section>
                                            </div>
                                            <p
                                                v-if="event.message"
                                                class="mt-2 rounded-md bg-rose-50 px-2 py-1.5 text-xs font-semibold text-rose-700"
                                            >
                                                {{ event.message }}
                                            </p>
                                        </article>

                                        <div
                                            v-if="providerEvents(item).length === 0"
                                            class="rounded-lg border border-dashed border-slate-300 px-3 py-4 text-center text-xs text-slate-500"
                                        >
                                            Dữ liệu cũ chưa lưu snapshot request/response. Trạng thái chuẩn hóa vẫn hiển thị phía trên.
                                        </div>

                                        <p v-if="item.message" class="rounded-md bg-rose-50 px-2 py-1.5 text-xs font-semibold text-rose-700">
                                            {{ item.message }}
                                        </p>
                                        <p v-if="item.last_checked_at" class="text-[11px] text-slate-500">
                                            Cập nhật gần nhất: {{ formatDateTime(item.last_checked_at) }}
                                        </p>
                                    </div>
                                </section>
                            </div>
                        </article>
                        <div
                            v-if="!displayOrder.recipients?.length"
                            class="grid min-h-32 place-items-center rounded-xl border border-dashed border-slate-300 text-center"
                        >
                            <div>
                                <PackageOpen class="mx-auto h-7 w-7 text-slate-300" />
                                <p class="mt-2 text-sm text-slate-500">Chưa có dữ liệu lượt nạp.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <template v-if="displayOrder" #footer>
            <footer class="flex w-full items-center justify-end gap-2 border-t border-slate-100 bg-white px-5 py-4 sm:px-6">
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="emit('close')"
                >
                    Đóng
                </button>
                <button
                    v-if="primaryAction && primaryAction.action !== 'detail'"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="acting"
                    @click="emit('action', primaryAction.action)"
                >
                    <LoaderCircle v-if="acting" class="mr-2 h-4 w-4 animate-spin" />{{ primaryAction.label }}
                </button>
            </footer>
        </template>
    </Modal>
</template>
