<script setup lang="ts">
import Modal from '@/components/shared/Modal/index.vue';
import { Copy, LoaderCircle, PackageOpen } from 'lucide-vue-next';
import { computed } from 'vue';
import type { ActionOption, OrderAction, OrderRow, RecipientRow } from '../types';
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
</script>

<template>
    <Modal :model-value="open" panel-class="max-w-[920px]" @update:model-value="!$event && emit('close')">
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
            <div v-else-if="displayOrder" class="grid gap-5 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <div class="space-y-5">
                    <section class="grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4">
                        <div>
                            <p class="text-xs font-medium text-slate-500">Thanh toán</p>
                            <OrderStatusBadge class="mt-2" kind="payment" :status="displayOrder.payment_status" />
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-500">Provider</p>
                            <OrderStatusBadge class="mt-2" kind="order" :status="displayOrder.order_status" />
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
