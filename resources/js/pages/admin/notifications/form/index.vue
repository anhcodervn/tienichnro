<script setup lang="ts">
import { adminNotificationService } from '@/services/admin-notification.service';
import type { AdminNotificationPayload } from '@/types/notification.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const submitting = ref(false);

const notificationId = computed(() => {
    const value = route.params.notification_id;
    if (!value) {
        return null;
    }

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : null;
});

const isEdit = computed(() => notificationId.value !== null);

const form = reactive<AdminNotificationPayload>({
    scope: 'system',
    user_id: null,
    title: '',
    content: '',
    redirect_url: '',
    type: '',
});

watch(
    () => form.scope,
    (scope) => {
        if (scope === 'system') {
            form.user_id = null;
        }
    },
);

const loadDetail = async (): Promise<void> => {
    if (!notificationId.value) {
        return;
    }

    try {
        loading.value = true;
        const detail = await adminNotificationService.get(notificationId.value);
        form.scope = detail.scope;
        form.user_id = detail.user_id;
        form.title = detail.title;
        form.content = detail.content;
        form.redirect_url = detail.redirect_url ?? '';
        form.type = detail.type ?? '';
    } catch (error) {
        handleErrorResponse(error);
        router.push({ name: 'admin.notifications.index' });
    } finally {
        loading.value = false;
    }
};

const submit = async (): Promise<void> => {
    try {
        submitting.value = true;

        const payload: AdminNotificationPayload = {
            scope: form.scope,
            user_id: form.scope === 'user' ? Number(form.user_id) : null,
            title: form.title,
            content: form.content,
            redirect_url: form.redirect_url || null,
            type: form.type || null,
        };

        if (isEdit.value && notificationId.value) {
            await adminNotificationService.update(notificationId.value, payload);
            handleSuccessResponse({ data: { status: true, message: 'Cập nhật thông báo thành công.' } });
        } else {
            await adminNotificationService.create(payload);
            handleSuccessResponse({ data: { status: true, message: 'Tạo thông báo thành công.' } });
        }

        router.push({ name: 'admin.notifications.index' });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        submitting.value = false;
    }
};

onMounted(loadDetail);
</script>

<template>
    <div class="space-y-4">
        <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <h1 class="text-xl font-bold text-slate-950">{{ isEdit ? 'Cập nhật thông báo' : 'Tạo thông báo' }}</h1>
            <p class="mt-1 text-sm text-slate-500">Chọn phạm vi hệ thống hoặc người dùng cụ thể.</p>
        </section>

        <section v-if="loading" class="rounded-[10px] border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">Đang tải dữ liệu...</section>

        <section v-else class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-sm font-semibold text-slate-700">Phạm vi</span>
                        <select v-model="form.scope" class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400">
                            <option value="system">Toàn hệ thống</option>
                            <option value="user">Người dùng cụ thể</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm font-semibold text-slate-700">Type</span>
                        <input
                            v-model="form.type"
                            type="text"
                            class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                            placeholder="system, payment, topup..."
                        />
                    </label>
                </div>

                <label v-if="form.scope === 'user'" class="block space-y-1">
                    <span class="text-sm font-semibold text-slate-700">User ID</span>
                    <input
                        v-model.number="form.user_id"
                        type="number"
                        min="1"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        required
                    />
                </label>

                <label class="block space-y-1">
                    <span class="text-sm font-semibold text-slate-700">Tiêu đề</span>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        maxlength="255"
                        required
                    />
                </label>

                <label class="block space-y-1">
                    <span class="text-sm font-semibold text-slate-700">Nội dung</span>
                    <textarea
                        v-model="form.content"
                        rows="5"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        maxlength="5000"
                        required
                    />
                </label>

                <label class="block space-y-1">
                    <span class="text-sm font-semibold text-slate-700">Link chuyển hướng (tuỳ chọn)</span>
                    <input
                        v-model="form.redirect_url"
                        type="url"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        placeholder="https://napcarot.vn/huong-dan"
                    />
                </label>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Đang lưu...' : isEdit ? 'Cập nhật' : 'Tạo mới' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center rounded-[10px] border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="router.push({ name: 'admin.notifications.index' })"
                    >
                        Hủy
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>
