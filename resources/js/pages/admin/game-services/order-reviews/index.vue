<script setup lang="ts">
import SecondaryPasswordDialog from '@/components/shared/SecondaryPasswordDialog.vue';
import {
    adminGameServiceService,
    type GameServiceOrder,
    type GameServiceOrderProgress,
    type GameServiceOrderStatus,
} from '@/services/admin-game-service.service';
import { gameServiceSecondaryAuthService } from '@/services/game-service-secondary-auth.service';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { CircleCheckBig, ClipboardCheck, Eye, ImageIcon, LoaderCircle, LockKeyhole, Save, Search, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';

type ReviewTab = 'information' | 'progress' | 'completion';

const orders = ref<GameServiceOrder[]>([]);
const loading = ref(false);
const detailLoading = ref(false);
const saving = ref(false);
const approving = ref(false);
const selectedOrder = ref<GameServiceOrder | null>(null);
const progressUpdates = ref<GameServiceOrderProgress[]>([]);
const activeTab = ref<ReviewTab>('information');
const filters = reactive({ search: '' });
const editForm = reactive({ status: 'review' as GameServiceOrderStatus, admin_note: '' });

const secondaryDialogOpen = ref(false);
const secondaryConfigured = ref(false);
const secondaryUnlocked = ref(false);
const secondaryUnlocking = ref(false);
const secondaryStatusChecking = ref(true);
const payloadLoading = ref(false);

const inputClass =
    'min-h-11 w-full rounded-md border-2 border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-950 outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-4 focus:ring-indigo-100';
const tabs: Array<{ key: ReviewTab; label: string }> = [
    { key: 'information', label: '1. Thông tin đơn' },
    { key: 'progress', label: '2. Tiến trình' },
    { key: 'completion', label: '3. Báo cáo hoàn thành' },
];
const reviewStatusLabels: Partial<Record<GameServiceOrderStatus, string>> = {
    review: 'Chờ admin duyệt',
    processing: 'Trả lại CTV xử lý',
};
const progressOnly = computed(() => progressUpdates.value.filter((update) => update.type === 'progress'));
const completionReports = computed(() =>
    progressUpdates.value
        .filter((update) => update.type === 'completion')
        .slice()
        .reverse(),
);
const money = (value: number): string => `${new Intl.NumberFormat('vi-VN').format(value)}đ`;
const dateTime = (value: string | null): string =>
    value ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value)) : '—';
const payloadValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return '—';
    return typeof value === 'object' ? JSON.stringify(value) : String(value);
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        const response = await adminGameServiceService.orders({
            search: filters.search || undefined,
            status: 'review',
            per_page: 100,
        });
        orders.value = response.data.data.data;
        window.dispatchEvent(new CustomEvent('game-service-order-review-count', { detail: Number(response.data.data.total ?? 0) }));
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const openOrder = async (order: GameServiceOrder): Promise<void> => {
    selectedOrder.value = order;
    activeTab.value = 'information';
    progressUpdates.value = [];
    detailLoading.value = true;

    try {
        const [orderResponse, progress] = await Promise.all([
            adminGameServiceService.order(order.code),
            adminGameServiceService.orderProgress(order.code),
        ]);
        selectedOrder.value = orderResponse.data.data;
        progressUpdates.value = progress;
        editForm.status = selectedOrder.value?.status ?? 'review';
        editForm.admin_note = selectedOrder.value?.admin_note ?? '';
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        detailLoading.value = false;
    }
};

const closeOrder = (): void => {
    selectedOrder.value = null;
    progressUpdates.value = [];
};

const checkSecondaryAuth = async (): Promise<void> => {
    secondaryStatusChecking.value = true;
    try {
        const status = await gameServiceSecondaryAuthService.status();
        secondaryConfigured.value = status.configured;
        secondaryUnlocked.value = status.unlocked;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        secondaryStatusChecking.value = false;
    }
};

const loadSelectedPayload = async (): Promise<void> => {
    if (!selectedOrder.value) return;

    payloadLoading.value = true;
    const selectedCode = selectedOrder.value.code;
    try {
        const payload = await adminGameServiceService.orderPayload(selectedCode);
        if (selectedOrder.value?.code === selectedCode) {
            selectedOrder.value = { ...selectedOrder.value, payload, payload_locked: false };
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        payloadLoading.value = false;
    }
};

const requestPayload = (): void => {
    if (secondaryUnlocked.value) {
        void loadSelectedPayload();
        return;
    }
    secondaryDialogOpen.value = true;
};

const unlockSecondaryPassword = async (password: string): Promise<void> => {
    secondaryUnlocking.value = true;
    try {
        await gameServiceSecondaryAuthService.unlock(password);
        secondaryConfigured.value = true;
        secondaryUnlocked.value = true;
        secondaryDialogOpen.value = false;
        await loadSelectedPayload();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        secondaryUnlocking.value = false;
    }
};

const lockSecondarySession = (): void => {
    secondaryUnlocked.value = false;
    if (selectedOrder.value) {
        selectedOrder.value = { ...selectedOrder.value, payload: {}, payload_locked: true };
    }
};

const saveReviewStatus = async (): Promise<void> => {
    if (!selectedOrder.value) return;

    saving.value = true;
    try {
        const response = await adminGameServiceService.updateOrder(selectedOrder.value.code, {
            status: editForm.status,
            admin_note: editForm.admin_note || null,
        });
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật trạng thái đơn.' } });

        if (response.data.data.status !== 'review') {
            closeOrder();
            await load();
            return;
        }

        selectedOrder.value = response.data.data;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const approveCompletion = async (): Promise<void> => {
    if (!selectedOrder.value || completionReports.value.length === 0) return;

    const confirmation = await Swal.fire({
        icon: 'question',
        title: 'Xác nhận hoàn thành?',
        text: 'Đơn sẽ được hoàn thành và tiền công được kết toán cho cộng tác viên.',
        showCancelButton: true,
        confirmButtonText: 'Xác nhận hoàn thành',
        cancelButtonText: 'Xem lại',
        confirmButtonColor: '#059669',
    });
    if (!confirmation.isConfirmed) return;

    approving.value = true;
    try {
        await adminGameServiceService.approveOrderCompletion(selectedOrder.value.code, {
            admin_note: editForm.admin_note || null,
        });
        handleSuccessResponse({ data: { status: true, message: 'Đã xác nhận đơn hoàn thành và kết toán cho CTV.' } });
        closeOrder();
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        approving.value = false;
    }
};

onMounted(() => {
    window.addEventListener('game-service-secondary-auth:locked', lockSecondarySession);
    void Promise.all([load(), checkSecondaryAuth()]);
});
onBeforeUnmount(() => {
    window.removeEventListener('game-service-secondary-auth:locked', lockSecondarySession);
});
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header
            class="flex flex-col gap-4 rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-lg bg-emerald-600 text-white"><ClipboardCheck class="size-6" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">Dịch vụ game</p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">Đơn CTV báo hoàn thành</h1>
                    <p class="mt-1 text-sm text-slate-600">Kiểm tra thông tin, tiến trình và bằng chứng trước khi xác nhận kết toán.</p>
                </div>
            </div>
            <form class="flex w-full gap-2 lg:max-w-md" @submit.prevent="load">
                <label class="relative min-w-0 flex-1">
                    <Search class="absolute left-3 top-3.5 size-4 text-slate-400" />
                    <input v-model.trim="filters.search" :class="[inputClass, 'pl-9']" placeholder="Mã đơn, email, dịch vụ..." />
                </label>
                <button class="min-h-11 rounded-md bg-slate-950 px-5 text-sm font-bold text-white">Tìm kiếm</button>
            </form>
        </header>

        <div v-if="loading" class="grid min-h-64 place-items-center rounded-lg border border-slate-200 bg-white">
            <LoaderCircle class="size-8 animate-spin text-slate-400" />
        </div>
        <section v-else class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-[900px] text-left text-sm" data-completion-review-table>
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Đơn</th>
                            <th class="px-4 py-3">Game / dịch vụ</th>
                            <th class="px-4 py-3">Gói</th>
                            <th class="px-4 py-3">CTV thực hiện</th>
                            <th class="px-4 py-3 text-right">Tổng tiền</th>
                            <th class="px-4 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="order in orders" :key="order.id" class="hover:bg-slate-50">
                            <td class="px-4 py-4">
                                <strong class="font-mono text-emerald-700">{{ order.code }}</strong>
                                <p class="mt-1 text-xs text-slate-500">{{ order.email || 'Không có email' }} · {{ dateTime(order.created_at) }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <strong>{{ order.game_name }}</strong>
                                <p class="mt-1 text-xs text-slate-500">{{ order.service_name }} · {{ order.server_name || 'Không chọn máy chủ' }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <strong>{{ order.package_name }}</strong>
                                <p class="mt-1 text-xs text-slate-500">{{ order.price_label }} · SL {{ order.quantity }}</p>
                            </td>
                            <td class="px-4 py-4 font-bold">{{ order.collaborator?.name || 'Chưa có CTV' }}</td>
                            <td class="px-4 py-4 text-right font-black text-emerald-700">{{ money(order.total_amount) }}</td>
                            <td class="px-4 py-4 text-right">
                                <button type="button" class="inline-flex items-center gap-1 font-bold text-indigo-700" @click="openOrder(order)">
                                    <Eye class="size-4" /> Xem
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.length === 0">
                            <td colspan="6" class="px-4 py-14 text-center text-slate-500">Không có đơn nào đang chờ duyệt hoàn thành.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div v-if="selectedOrder" class="fixed inset-0 z-[90] grid place-items-center bg-slate-950/55 p-3" @click.self="closeOrder">
            <section class="flex max-h-[calc(100dvh-1.5rem)] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 p-4 sm:p-5">
                    <div>
                        <p class="font-mono text-sm font-bold text-emerald-700">{{ selectedOrder.code }}</p>
                        <h2 class="mt-1 text-xl font-black">{{ selectedOrder.service_name }}</h2>
                        <p class="text-sm text-slate-500">{{ selectedOrder.game_name }} · {{ selectedOrder.package_name }}</p>
                    </div>
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-md border border-slate-200" @click="closeOrder">
                        <X class="size-5" />
                    </button>
                </header>

                <nav class="grid grid-cols-3 border-b border-slate-200 bg-slate-50 p-2" aria-label="Chi tiết duyệt đơn">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="min-h-11 rounded-md px-2 text-xs font-black transition sm:text-sm"
                        :class="
                            activeTab === tab.key
                                ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-500 hover:text-slate-900'
                        "
                        :aria-selected="activeTab === tab.key"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div v-if="detailLoading" class="grid min-h-80 place-items-center">
                    <LoaderCircle class="size-8 animate-spin text-slate-400" />
                </div>
                <div v-else class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-5">
                    <section v-if="activeTab === 'information'" class="grid gap-5 lg:grid-cols-2">
                        <div class="grid content-start gap-4">
                            <dl class="grid gap-2 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Khách hàng</dt>
                                    <dd class="font-bold">{{ selectedOrder.email || '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">CTV xử lý</dt>
                                    <dd class="font-bold">{{ selectedOrder.collaborator?.name || '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Máy chủ</dt>
                                    <dd class="font-bold">{{ selectedOrder.server_name || '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Đơn giá</dt>
                                    <dd class="font-bold">{{ money(selectedOrder.unit_price) }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Số lượng</dt>
                                    <dd class="font-bold">{{ selectedOrder.quantity }}</dd>
                                </div>
                                <div class="flex justify-between gap-3 border-t border-slate-200 pt-2">
                                    <dt class="font-bold">Tổng tiền</dt>
                                    <dd class="text-lg font-black text-emerald-700">{{ money(selectedOrder.total_amount) }}</dd>
                                </div>
                            </dl>

                            <div>
                                <h3 class="mb-2 text-sm font-black">Payload khách gửi</h3>
                                <div
                                    v-if="selectedOrder.payload_locked"
                                    class="grid min-h-32 place-items-center gap-3 rounded-md border border-dashed border-slate-300 bg-slate-50 p-4 text-center"
                                >
                                    <LockKeyhole class="size-7 text-slate-500" />
                                    <p class="text-sm font-bold text-slate-700">Thông tin đang được ẩn bằng mật khẩu C2.</p>
                                    <button
                                        type="button"
                                        :disabled="payloadLoading || secondaryStatusChecking"
                                        class="inline-flex min-h-10 items-center gap-2 rounded-md bg-indigo-600 px-4 text-sm font-bold text-white disabled:opacity-50"
                                        @click="requestPayload"
                                    >
                                        <LoaderCircle v-if="payloadLoading" class="size-4 animate-spin" />
                                        <Eye v-else class="size-4" /> Xem payload
                                    </button>
                                </div>
                                <dl v-else class="grid gap-2 rounded-md border border-slate-200 p-4 text-sm">
                                    <div v-for="(value, key) in selectedOrder.payload" :key="key" class="grid grid-cols-[8rem_minmax(0,1fr)] gap-3">
                                        <dt class="break-words font-mono text-xs text-slate-500">{{ key }}</dt>
                                        <dd class="break-words font-bold text-slate-900">{{ payloadValue(value) }}</dd>
                                    </div>
                                    <p v-if="Object.keys(selectedOrder.payload).length === 0" class="text-slate-500">Đơn không có payload.</p>
                                </dl>
                            </div>
                        </div>

                        <form class="grid content-start gap-4 rounded-md border border-amber-200 bg-amber-50 p-4" @submit.prevent="saveReviewStatus">
                            <div>
                                <h3 class="font-black text-slate-950">Điều chỉnh đơn chưa đạt yêu cầu</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600">
                                    Nếu kết quả chưa đạt, trả đơn về đang xử lý để đúng CTV hiện tại tiếp tục làm và gửi lại báo cáo hoàn thành. Xác
                                    nhận hoàn thành chỉ thực hiện ở tab báo cáo.
                                </p>
                            </div>
                            <label class="grid gap-1 text-sm font-bold">
                                Trạng thái
                                <select v-model="editForm.status" :class="inputClass">
                                    <option v-for="(label, status) in reviewStatusLabels" :key="status" :value="status">{{ label }}</option>
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm font-bold">
                                Ghi chú quản trị
                                <textarea
                                    v-model="editForm.admin_note"
                                    rows="6"
                                    :class="inputClass"
                                    placeholder="Lý do cần bổ sung hoặc chưa đạt yêu cầu..."
                                ></textarea>
                            </label>
                            <p class="text-xs leading-5 text-slate-500">Bắt đầu xử lý: {{ dateTime(selectedOrder.processing_at) }}</p>
                            <button
                                :disabled="saving"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-slate-950 px-4 font-bold text-white disabled:opacity-50"
                            >
                                <LoaderCircle v-if="saving" class="size-4 animate-spin" />
                                <Save v-else class="size-4" /> {{ saving ? 'Đang lưu...' : 'Lưu trạng thái đơn' }}
                            </button>
                        </form>
                    </section>

                    <section v-else-if="activeTab === 'progress'" class="grid gap-3" data-progress-readonly>
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-black text-slate-950">Tiến trình CTV cập nhật</h3>
                                <p class="mt-1 text-sm text-slate-500">Khu vực chỉ xem, quản trị viên không thể thêm hoặc sửa tiến trình.</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Chỉ xem</span>
                        </div>
                        <article v-for="update in progressOnly" :key="update.id" class="rounded-md border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <strong>{{ update.author?.name || 'Tài khoản đã xóa' }}</strong>
                                <time class="text-xs text-slate-500">{{ dateTime(update.created_at) }}</time>
                            </div>
                            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ update.description }}</p>
                            <a v-if="update.image_url" :href="update.image_url" target="_blank" rel="noopener" class="mt-3 block w-fit">
                                <img
                                    :src="update.image_url"
                                    alt="Ảnh cập nhật tiến trình"
                                    class="max-h-80 rounded-md border border-slate-200 object-contain"
                                />
                            </a>
                        </article>
                        <p
                            v-if="progressOnly.length === 0"
                            class="rounded-md border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500"
                        >
                            CTV chưa gửi cập nhật tiến trình nào.
                        </p>
                    </section>

                    <section v-else class="grid gap-4">
                        <div>
                            <h3 class="font-black text-slate-950">Báo cáo hoàn thành</h3>
                            <p class="mt-1 text-sm text-slate-500">Đối chiếu mô tả và ảnh xác minh trước khi duyệt kết toán.</p>
                        </div>
                        <article
                            v-for="(report, index) in completionReports"
                            :key="report.id"
                            class="rounded-md border p-4"
                            :class="index === 0 ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-slate-50'"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <CircleCheckBig class="size-5 text-emerald-600" />
                                    <strong>{{ index === 0 ? 'Báo cáo mới nhất' : 'Báo cáo trước đó' }}</strong>
                                </div>
                                <time class="text-xs text-slate-500">{{ dateTime(report.created_at) }}</time>
                            </div>
                            <p class="mt-1 text-xs font-bold text-slate-500">Người gửi: {{ report.author?.name || 'Tài khoản đã xóa' }}</p>
                            <p class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-800">{{ report.description }}</p>
                            <a v-if="report.image_url" :href="report.image_url" target="_blank" rel="noopener" class="mt-4 block w-fit">
                                <img
                                    :src="report.image_url"
                                    alt="Ảnh xác minh hoàn thành"
                                    class="max-h-[28rem] rounded-md border border-emerald-200 bg-white object-contain"
                                />
                            </a>
                            <div
                                v-else
                                class="mt-4 flex items-center gap-2 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm font-bold text-rose-700"
                            >
                                <ImageIcon class="size-4" /> Báo cáo thiếu ảnh xác minh.
                            </div>
                        </article>
                        <p
                            v-if="completionReports.length === 0"
                            class="rounded-md border border-dashed border-rose-300 bg-rose-50 p-8 text-center text-sm text-rose-700"
                        >
                            Không tìm thấy báo cáo hoàn thành hợp lệ cho đơn này.
                        </p>
                        <div class="sticky bottom-0 flex justify-end border-t border-slate-200 bg-white pt-4">
                            <button
                                type="button"
                                :disabled="approving || completionReports.length === 0"
                                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-600 px-5 font-black text-white shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                                @click="approveCompletion"
                            >
                                <LoaderCircle v-if="approving" class="size-4 animate-spin" />
                                <CircleCheckBig v-else class="size-4" /> {{ approving ? 'Đang xác nhận...' : 'Xác nhận hoàn thành' }}
                            </button>
                        </div>
                    </section>
                </div>
            </section>
        </div>

        <SecondaryPasswordDialog
            :open="secondaryDialogOpen"
            :configured="secondaryConfigured"
            :loading="secondaryUnlocking"
            @close="secondaryDialogOpen = false"
            @submit="unlockSecondaryPassword"
        />
    </main>
</template>
