<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { nroNotificationService, type NroBoss, type NroBossPayload } from '@/services/nro-notification.service';
import { handleErrorResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { nextTick, onMounted, reactive, ref } from 'vue';

const bosses = ref<NroBoss[]>([]);
const editingId = ref<number | null>(null);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const showForm = ref(false);
const errors = ref<string[]>([]);
const bossNameInput = ref<HTMLInputElement | null>(null);
const draft = reactive<NroBossPayload>({ code: '', name: '', game_names: '', respawn_seconds: null, is_active: true, sort_order: 0 });
const resetDraft = () => {
    editingId.value = null;
    errors.value = [];
    Object.assign(draft, { code: '', name: '', game_names: '', respawn_seconds: null, is_active: true, sort_order: 0 });
};
const add = () => {
    if (saving.value || deletingId.value !== null) return;
    resetDraft();
    showForm.value = true;
};
const close = () => {
    if (saving.value) return;
    showForm.value = false;
    resetDraft();
};
const edit = (boss: NroBoss) => {
    if (saving.value || deletingId.value !== null) return;
    errors.value = [];
    editingId.value = boss.id;
    Object.assign(draft, {
        code: boss.code,
        name: boss.name,
        game_names: (boss.game_names ?? [boss.name]).join(', '),
        respawn_seconds: boss.respawn_seconds,
        is_active: boss.is_active,
        sort_order: boss.sort_order,
    });
    showForm.value = true;
};
const load = async () => {
    bosses.value = await nroNotificationService.bosses();
};
const remove = async (boss: NroBoss) => {
    if (saving.value || deletingId.value !== null) return;
    deletingId.value = boss.id;
    try {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Xóa cấu hình Boss?',
            text: `${boss.name} (${boss.code}) sẽ ngừng nhận lượt xuất hiện mới. Lịch sử thông báo vẫn được giữ.`,
            showCancelButton: true,
            confirmButtonText: 'Xóa Boss',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc2626',
        });
        if (!result.isConfirmed) return;
        await nroNotificationService.deleteBoss(boss.id);
        bosses.value = bosses.value.filter((item) => item.id !== boss.id);
        void Swal.fire({ icon: 'success', title: 'Đã xóa cấu hình Boss', text: boss.name });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
};
const save = async () => {
    if (!showForm.value || saving.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const response = await nroNotificationService.save(
            {
                ...draft,
                respawn_seconds: draft.respawn_seconds === null || String(draft.respawn_seconds) === '' ? null : Number(draft.respawn_seconds),
            },
            editingId.value,
        );
        const updated: NroBoss = response.data.data;
        bosses.value = [...bosses.value.filter((boss) => boss.id !== updated.id), updated].sort((left, right) =>
            left.sort_order - right.sort_order || left.name.localeCompare(right.name, 'vi') || left.id - right.id,
        );
        showForm.value = false;
        resetDraft();
        await nextTick();
        void Swal.fire({ icon: 'success', title: 'Đã lưu cấu hình Boss', text: updated.name });
    } catch (error) {
        if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
            errors.value = error.response?.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response?.data.message || 'Không lưu được Boss. Vui lòng thử lại.'];
        } else {
            errors.value = ['Không lưu được Boss. Vui lòng thử lại.'];
        }
    } finally {
        saving.value = false;
    }
};
onMounted(async () => {
    try {
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
});
</script>

<template>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6">
        <Breadcrumb :items="[{ label: 'Quản lý boss' }]" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Quản lý boss</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Cấu hình tên boss và thời gian hồi sinh. Dữ liệu xuất hiện, bị tiêu diệt được nhận qua API từ game để hiển thị cho client.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-3">
                <button
                    type="button"
                    :disabled="saving || deletingId !== null"
                    class="rounded-[10px] border border-emerald-700 bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800"
                    @click="add"
                >
                    Thêm Boss
                </button>
                <a
                    href="/thong-bao-game"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex shrink-0 items-center justify-center rounded-[10px] border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-slate-600 dark:bg-slate-900 dark:text-emerald-400 dark:hover:bg-slate-800"
                    >Xem thông báo game ↗</a
                >
            </div>
        </div>
        <Dialog :open="showForm" :initial-focus="bossNameInput" class="relative z-[60]" @close="close">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel
                        class="max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-[10px] border border-slate-300 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900"
                    >
                        <form class="flex min-w-0 flex-col" @submit.prevent="save">
                            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-700 sm:px-6">
                                <div class="flex items-center justify-between gap-4">
                                    <DialogTitle class="text-lg font-bold text-slate-900 dark:text-white">{{
                                        editingId ? 'Sửa Boss' : 'Thêm Boss'
                                    }}</DialogTitle>
                                    <button
                                        type="button"
                                        :disabled="saving"
                                        aria-label="Đóng hộp thoại"
                                        class="flex size-9 shrink-0 items-center justify-center rounded-[10px] border border-slate-300 text-xl text-slate-600 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
                                        @click="close"
                                    >
                                        ×
                                    </button>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Một mã Boss có thể nhận nhiều tên trong game và dùng chung thời gian hồi sinh.
                                </p>
                            </div>
                            <div
                                v-if="errors.length"
                                role="alert"
                                class="mx-5 mt-4 rounded-[10px] border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                            >
                                <p v-for="(error, index) in errors" :key="index">{{ error }}</p>
                            </div>
                            <fieldset :disabled="saving" class="min-w-0">
                                <div class="grid min-w-0 gap-5 p-5 sm:grid-cols-2 sm:p-6">
                                    <label class="block min-w-0 text-sm font-semibold text-slate-700 dark:text-slate-200 sm:col-span-2">
                                        Tên nhóm Boss trong bộ lọc
                                        <input
                                            ref="bossNameInput"
                                            v-model="draft.name"
                                            required
                                            maxlength="100"
                                            placeholder="Fide Đại Ca"
                                            class="mt-2 block h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        />
                                    </label>
                                    <label class="block min-w-0 text-sm font-semibold text-slate-700 dark:text-slate-200 sm:col-span-2">
                                        Tên Boss trong game (cách nhau bằng dấu phẩy)
                                        <textarea
                                            v-model="draft.game_names"
                                            required
                                            rows="3"
                                            maxlength="5099"
                                            placeholder="Fide Đại Ca 1, Fide Đại Ca 2, Broly [x]"
                                            class="mt-2 block w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 py-2 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        ></textarea>
                                        <span class="mt-2 block text-xs font-normal text-slate-500 dark:text-slate-400"
                                            >Dùng [x] cho số bất kỳ: Broly [x] khớp Broly 1, Broly 27… Các tên cùng mã được lọc chung; mỗi boss tính hồi sinh từ lúc chết riêng.</span
                                        >
                                    </label>
                                    <label class="block min-w-0 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        Mã Boss
                                        <input
                                            v-model="draft.code"
                                            required
                                            maxlength="64"
                                            pattern="[A-Za-z0-9_-]+"
                                            placeholder="BROLY_3"
                                            class="mt-2 block h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        />
                                    </label>
                                    <label class="block min-w-0 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        Hồi sinh sau (giây)
                                        <input
                                            v-model.number="draft.respawn_seconds"
                                            type="number"
                                            min="0"
                                            max="31536000"
                                            placeholder="Không xác định"
                                            class="mt-2 block h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        />
                                    </label>
                                    <label class="block min-w-0 text-sm font-semibold text-slate-700 dark:text-slate-200 sm:col-span-2">
                                        Thứ tự bộ lọc
                                        <input
                                            v-model.number="draft.sort_order"
                                            type="number"
                                            required
                                            min="0"
                                            max="2147483647"
                                            step="1"
                                            class="mt-2 block h-11 w-full min-w-0 rounded-[10px] border border-slate-300 bg-white px-3 text-sm font-normal text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                        />
                                        <span class="mt-2 block text-xs font-normal text-slate-500 dark:text-slate-400">Số nhỏ đứng trước. Cùng số sẽ sắp xếp theo tên Boss. Mặc định: 0.</span>
                                    </label>
                                    <div class="space-y-3 sm:col-span-2">
                                        <label
                                            class="flex w-fit cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-700 dark:text-slate-200"
                                            ><input v-model="draft.is_active" type="checkbox" class="size-4 shrink-0 accent-emerald-700" /> Nhận thông
                                            báo Boss này</label
                                        >
                                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-400">
                                            1800 giây = 30 phút. Để trống nếu chưa biết thời gian hồi sinh. Thay đổi chỉ áp dụng cho lần Boss chết
                                            tiếp theo; lịch sử được giữ nguyên.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="mt-auto flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/50 sm:flex-row sm:justify-end sm:px-6"
                                >
                                    <button
                                        type="button"
                                        class="rounded-[10px] border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
                                        @click="close"
                                    >
                                        Hủy
                                    </button>
                                    <button
                                        :disabled="saving"
                                        class="rounded-[10px] bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                                    >
                                        {{ saving ? 'Đang lưu…' : 'Lưu Boss' }}
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>

        <section
            class="min-w-0 overflow-hidden rounded-[10px] border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900"
            aria-labelledby="boss-list-title"
        >
            <div class="flex items-center justify-between gap-3 border-b border-slate-300 px-5 py-4 dark:border-slate-600 sm:px-6">
                <h2 id="boss-list-title" class="text-lg font-bold text-slate-900 dark:text-white">Danh sách Boss</h2>
                <span class="shrink-0 rounded-[10px] bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >{{ bosses.length }} Boss</span
                >
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-sm text-slate-700 dark:text-slate-200">
                    <thead
                        class="border-b border-slate-300 bg-slate-50 text-xs uppercase tracking-wide text-slate-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
                    >
                        <tr class="divide-x divide-slate-300 dark:divide-slate-600">
                            <th scope="col" class="px-5 py-3 sm:px-6">Boss</th>
                            <th scope="col" class="px-4 py-3">Mã Boss</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Hồi sinh</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Thứ tự bộ lọc</th>
                            <th scope="col" class="whitespace-nowrap px-4 py-3">Trạng thái</th>
                            <th scope="col" class="px-5 py-3 text-right sm:px-6">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="boss in bosses"
                            :key="boss.id"
                            class="divide-x divide-slate-300 border-t border-slate-300 hover:bg-slate-50 dark:divide-slate-600 dark:border-slate-600 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-5 py-4 font-semibold text-slate-900 dark:text-white sm:px-6">
                                {{ boss.name }}
                                <p class="mt-1 max-w-sm break-words text-xs font-normal text-slate-500 dark:text-slate-400">
                                    {{ (boss.game_names ?? [boss.name]).join(', ') }}
                                </p>
                            </td>
                            <td class="break-all px-4 py-4 font-mono text-xs">{{ boss.code }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                {{ boss.respawn_seconds === null ? 'Không xác định' : `${boss.respawn_seconds} giây` }}
                            </td>
                            <td class="px-4 py-4 tabular-nums">{{ boss.sort_order }}</td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex whitespace-nowrap rounded-[10px] px-2.5 py-1 text-xs font-semibold"
                                    :class="
                                        boss.is_active
                                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                                    "
                                    >{{ boss.is_active ? 'Hoạt động' : 'Tạm tắt' }}</span
                                >
                            </td>
                            <td class="px-5 py-4 text-right sm:px-6">
                                <button
                                    type="button"
                                    :disabled="saving || deletingId !== null"
                                    class="rounded-[10px] border border-slate-300 px-3 py-1.5 font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-slate-600 dark:text-emerald-400 dark:hover:bg-slate-800"
                                    @click="edit(boss)"
                                >
                                    Sửa
                                </button>
                                <button
                                    type="button"
                                    :disabled="saving || deletingId !== null"
                                    class="ml-2 rounded-[10px] border border-red-200 px-3 py-1.5 font-semibold text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950"
                                    @click="remove(boss)"
                                >
                                    {{ deletingId === boss.id ? 'Đang xóa…' : 'Xóa' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!bosses.length">
                            <td colspan="6" class="px-6 py-12 text-center">
                                <p class="font-semibold text-slate-700 dark:text-slate-200">Chưa có Boss nào</p>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Bấm Thêm Boss để tạo cấu hình đầu tiên.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
