<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import { nroNotificationService, type NroNotificationGroup, type NroNotificationType } from '@/services/nro-notification.service';
import { handleErrorResponse } from '@/utils/response';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/vue';
import { isAxiosError } from 'axios';
import Swal from 'sweetalert2';
import { nextTick, onMounted, reactive, ref } from 'vue';

const types = ref<NroNotificationType[]>([]);
const groups = ref<NroNotificationGroup[]>([]);
const editing = ref<NroNotificationType | null>(null);
const showForm = ref(false);
const draft = reactive<{ code: string; name: string; type_id: number; additional_filters: ('boss' | 'state')[]; keywords: string }>({
    code: '',
    name: '',
    type_id: 0,
    additional_filters: [],
    keywords: '',
});
const loading = ref(true);
const failed = ref(false);
const saving = ref(false);
const deletingId = ref<number | null>(null);
const errors = ref<string[]>([]);
const nameInput = ref<HTMLInputElement | null>(null);
const close = () => {
    if (saving.value) return;
    showForm.value = false;
    editing.value = null;
    errors.value = [];
};
const add = () => {
    errors.value = [];
    editing.value = null;
    Object.assign(draft, {
        code: '',
        name: '',
        type_id: groups.value.find((group) => group.code === 'general')?.id ?? groups.value[0]?.id ?? 0,
        additional_filters: [],
        keywords: '',
    });
    showForm.value = true;
};
const edit = (type: NroNotificationType) => {
    errors.value = [];
    editing.value = type;
    Object.assign(draft, {
        code: type.code,
        name: type.name,
        type_id: type.type_id,
        additional_filters: [...(type.additional_filters ?? [])],
        keywords: (type.keywords ?? []).join(', '),
    });
    showForm.value = true;
};
const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        const result = await nroNotificationService.notificationTypes();
        types.value = result.codes;
        groups.value = result.groups;
    } catch (error) {
        failed.value = true;
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};
const save = async () => {
    if (!showForm.value || saving.value) return;
    saving.value = true;
    errors.value = [];
    try {
        const updated = editing.value
            ? await nroNotificationService.updateNotificationType(editing.value.id, { ...draft })
            : await nroNotificationService.createNotificationType({ ...draft });
        types.value = [...types.value.filter((type) => type.id !== updated.id), updated].sort((left, right) => left.id - right.id);
        editing.value = null;
        showForm.value = false;
        await nextTick();
        void Swal.fire({ icon: 'success', title: 'Đã lưu loại thông báo', text: updated.name });
    } catch (error) {
        if (isAxiosError<{ message?: string; errors?: Record<string, string[]> }>(error)) {
            errors.value = error.response?.data.errors
                ? Object.values(error.response.data.errors).flat()
                : [error.response?.data.message || 'Không lưu được loại thông báo. Vui lòng thử lại.'];
        } else {
            errors.value = ['Không lưu được loại thông báo. Vui lòng thử lại.'];
        }
    } finally {
        saving.value = false;
    }
};
onMounted(load);

const remove = async (type: NroNotificationType) => {
    if (saving.value || deletingId.value !== null) return;
    deletingId.value = type.id;
    try {
        const result = await Swal.fire({
            icon: 'warning',
            title: 'Xóa loại thông báo?',
            text: `${type.name} (${type.code}) sẽ ngừng được dùng để nhận và phân loại thông báo mới. Lịch sử đã nhận vẫn được giữ.`,
            showCancelButton: true,
            confirmButtonText: 'Xóa loại',
            cancelButtonText: 'Hủy',
            confirmButtonColor: '#dc2626',
        });
        if (!result.isConfirmed) return;
        await nroNotificationService.deleteNotificationType(type.id);
        types.value = types.value.filter((item) => item.id !== type.id);
        void Swal.fire({ icon: 'success', title: 'Đã xóa loại thông báo', text: type.name });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        deletingId.value = null;
    }
};
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-6">
        <Breadcrumb :items="[{ label: 'Thông báo game' }, { label: 'Quản lý loại thông báo' }]" />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Quản lý loại thông báo</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Thêm loại, sửa tên hiển thị, nhóm và bộ lọc bổ sung. Bot gửi mã loại qua API để lưu thông báo từ game.
                </p>
            </div>
            <button
                type="button"
                :disabled="loading || failed || saving || deletingId !== null || !groups.length"
                class="shrink-0 rounded-[10px] bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50"
                @click="add"
            >
                Thêm loại thông báo
            </button>
        </div>
        <Dialog :open="showForm" :initial-focus="nameInput" class="relative z-[60]" @close="close">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <DialogPanel
                        class="max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-[10px] border border-slate-300 bg-white shadow-xl dark:border-slate-600 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                            <DialogTitle class="text-lg font-bold text-slate-900 dark:text-white">{{
                                editing ? `Sửa loại thông báo ${editing.code}` : 'Thêm loại thông báo'
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
                        <form class="p-5" @submit.prevent="save">
                            <div
                                v-if="errors.length"
                                role="alert"
                                class="mb-4 rounded-[10px] border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                            >
                                <p v-for="(message, index) in errors" :key="index">{{ message }}</p>
                            </div>
                            <fieldset :disabled="saving" class="grid gap-4 sm:grid-cols-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Tên hiển thị<input
                                        ref="nameInput"
                                        v-model="draft.name"
                                        required
                                        maxlength="100"
                                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                                /></label>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Mã loại<input
                                        v-model="draft.code"
                                        required
                                        maxlength="64"
                                        pattern="[A-Z][A-Z0-9_-]*"
                                        placeholder="GAME_EVENT"
                                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-800"
                                    /><span class="mt-2 block text-xs text-slate-500"
                                        >Chữ in hoa, số, gạch dưới hoặc gạch ngang. Nếu bot gửi code, hãy cập nhật bot khi đổi mã.</span
                                    ></label
                                >
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                    >Nhóm loại<select
                                        v-model.number="draft.type_id"
                                        required
                                        class="mt-2 h-11 w-full rounded-[10px] border border-slate-300 bg-white px-3 dark:border-slate-600 dark:bg-slate-800"
                                    >
                                        <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.name }}</option>
                                    </select></label
                                >
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200 sm:col-span-2"
                                    >Keyword phân loại
                                    <textarea
                                        v-model="draft.keywords"
                                        rows="3"
                                        maxlength="5050"
                                        placeholder="bảo trì, đóng máy chủ, tạm ngừng hoạt động"
                                        class="mt-2 w-full rounded-[10px] border border-slate-300 bg-white px-3 py-2 dark:border-slate-600 dark:bg-slate-800"
                                    ></textarea>
                                    <span class="mt-2 block text-xs leading-5 text-slate-500"
                                        >Các keyword cách nhau bằng dấu phẩy; chỉ cần khớp một keyword trong content. Không phân biệt chữ hoa/thường.
                                        Ưu tiên keyword dài nhất, nếu bằng nhau chọn loại có ID nhỏ hơn. Thông báo Boss đúng mẫu được xử lý
                                        trước.</span
                                    >
                                </label>
                                <div class="space-y-3 sm:col-span-2">
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Lọc bổ sung ở trang thông báo game</p>
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                        ><input v-model="draft.additional_filters" type="checkbox" value="boss" class="size-4 accent-emerald-700" />
                                        Danh sách boss</label
                                    >
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                        ><input v-model="draft.additional_filters" type="checkbox" value="state" class="size-4 accent-emerald-700" />
                                        Trạng thái boss</label
                                    >
                                    <p class="text-xs text-slate-500">
                                        Chỉ hiện các bộ lọc đã bật khi người dùng chọn loại này. Bỏ chọn cả hai để tắt lọc bổ sung.
                                    </p>
                                </div>
                                <div class="flex gap-3 sm:col-span-2">
                                    <button class="rounded-[10px] bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">
                                        {{ saving ? 'Đang lưu…' : 'Lưu thay đổi' }}</button
                                    ><button
                                        type="button"
                                        class="rounded-[10px] border border-slate-300 px-4 py-2.5 text-sm dark:border-slate-600 dark:text-white"
                                        @click="close"
                                    >
                                        Hủy
                                    </button>
                                </div>
                            </fieldset>
                        </form>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
        <section class="overflow-hidden rounded-[10px] border border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900">
            <p v-if="loading" role="status" class="p-8 text-center text-slate-500">Đang tải loại thông báo…</p>
            <div v-else-if="failed" class="p-8 text-center text-slate-500">
                Không tải được dữ liệu. <button class="text-emerald-700 underline" @click="load">Thử lại</button>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-left text-sm text-slate-700 dark:text-slate-200">
                    <thead class="border-b border-slate-300 bg-slate-50 dark:border-slate-600 dark:bg-slate-800">
                        <tr>
                            <th class="px-5 py-3">Mã loại</th>
                            <th class="px-5 py-3">Tên hiển thị</th>
                            <th class="px-5 py-3">Nhóm</th>
                            <th class="px-5 py-3">Keyword</th>
                            <th class="px-5 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="type in types" :key="type.id">
                            <td class="px-5 py-4 font-mono">{{ type.code }}</td>
                            <td class="px-5 py-4">{{ type.name }}</td>
                            <td class="px-5 py-4">{{ type.type.name }}</td>
                            <td class="max-w-xs whitespace-normal break-words px-5 py-4 text-xs">
                                {{ (type.keywords ?? []).join(', ') || 'Chưa cấu hình' }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    :disabled="saving || deletingId !== null"
                                    class="rounded-[10px] border border-slate-300 px-3 py-1.5 text-emerald-700 disabled:opacity-50 dark:border-slate-600 dark:text-emerald-400"
                                    @click="edit(type)"
                                >
                                    Sửa loại thông báo
                                </button>
                                <button
                                    type="button"
                                    :disabled="saving || deletingId !== null"
                                    class="ml-2 rounded-[10px] border border-red-300 px-3 py-1.5 text-red-700 disabled:opacity-50 dark:border-red-800 dark:text-red-400"
                                    @click="remove(type)"
                                >
                                    {{ deletingId === type.id ? 'Đang xử lý…' : 'Xóa' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!types.length">
                            <td colspan="5" class="p-8 text-center">Chưa có loại thông báo. Bấm Thêm loại thông báo để tạo loại mới.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
