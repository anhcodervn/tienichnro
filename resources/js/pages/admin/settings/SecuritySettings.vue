<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { SecuritySettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const saving = ref(false);
const form = ref<SecuritySettingType>({
    turnstile_enabled: false,
    turnstile_site_key: '',
    turnstile_secret_key: '',
    turnstile_secret_configured: false,
});

const loadSettings = async (): Promise<void> => {
    try {
        loading.value = true;
        const response = await adminSettingService.getSecurity();
        form.value = { ...form.value, ...response.settings, turnstile_secret_key: '' };
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const saveSettings = async (): Promise<void> => {
    try {
        saving.value = true;
        const response = await adminSettingService.updateSecurity({
            ...form.value,
            turnstile_site_key: form.value.turnstile_site_key.trim(),
            turnstile_secret_key: form.value.turnstile_secret_key.trim(),
        });
        form.value = { ...form.value, ...response.settings, turnstile_secret_key: '' };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật bảo vệ Cloudflare Turnstile.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(loadSettings);
</script>

<template>
    <div v-if="loading" class="space-y-3">
        <div class="h-20 animate-pulse rounded-[10px] bg-slate-100"></div>
        <div class="h-48 animate-pulse rounded-[10px] bg-slate-100"></div>
    </div>

    <div v-else class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
            <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Cloudflare Turnstile cho đơn hàng guest</h3>
                    <p class="text-sm text-slate-500">Xác minh thành viên trước khi mở dữ liệu thông báo game trực tiếp.</p>
                </div>
                <button
                    type="button"
                    class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                    :disabled="saving"
                    @click="saveSettings"
                >
                    {{ saving ? 'Đang lưu...' : 'Lưu bảo mật' }}
                </button>
            </div>

            <div class="space-y-4">
                <label class="flex items-center justify-between rounded-[10px] border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                    <span>
                        <strong class="block font-semibold text-slate-900">Bật Turnstile khi tạo đơn</strong>
                        <small class="mt-1 block text-slate-500">Thành viên xác minh để xem trực tiếp trong 15 phút. Admin được miễn xác minh.</small>
                    </span>
                    <input v-model="form.turnstile_enabled" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                </label>

                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-slate-600">Site Key</span>
                    <input
                        v-model="form.turnstile_site_key"
                        type="text"
                        autocomplete="off"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        placeholder="Nhập Site Key từ Cloudflare"
                    />
                </label>

                <label class="block space-y-1">
                    <span class="flex items-center justify-between gap-3 text-xs font-semibold text-slate-600">
                        <span>Secret Key</span>
                        <span
                            class="rounded-full px-2 py-0.5"
                            :class="form.turnstile_secret_configured ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                        >
                            {{ form.turnstile_secret_configured ? 'Đã cấu hình' : 'Chưa cấu hình' }}
                        </span>
                    </span>
                    <input
                        v-model="form.turnstile_secret_key"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                        :placeholder="form.turnstile_secret_configured ? 'Để trống để giữ Secret Key hiện tại' : 'Nhập Secret Key từ Cloudflare'"
                    />
                    <small class="block text-slate-500">Secret Key được mã hóa khi lưu và không được trả lại qua API quản trị.</small>
                </label>
            </div>
        </article>

        <aside class="rounded-[10px] border border-cyan-200 bg-cyan-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-700">Cách cấu hình</p>
            <ol class="mt-3 list-decimal space-y-3 pl-5 text-sm leading-6 text-slate-700">
                <li>Tạo widget Turnstile trong Cloudflare và khai báo domain production.</li>
                <li>Dán Site Key và Secret Key tương ứng vào đây.</li>
                <li>Lưu cấu hình rồi bật Turnstile và thử tạo một đơn khi chưa đăng nhập.</li>
            </ol>
            <p class="mt-4 rounded-[8px] border border-cyan-200 bg-white px-3 py-2 text-xs leading-5 text-slate-600">
                Backend xác minh token với Cloudflare trước khi cấp quyền. Nếu tắt Turnstile, thành viên chỉ xem được bản thông báo giới hạn.
            </p>
        </aside>
    </div>
</template>
