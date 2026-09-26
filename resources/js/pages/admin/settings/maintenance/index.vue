<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { MaintenanceSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { AlertTriangle, Gamepad2, Globe2, Save } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const isLoading = ref(true);
const isSaving = ref(false);
const form = ref<MaintenanceSettingType>({
    site_active: true,
    topup_maintenance_enabled: false,
    topup_maintenance_message: 'Cổng nạp game đang bảo trì. Vui lòng quay lại sau.',
});

const websiteMaintenanceEnabled = computed({
    get: () => !form.value.site_active,
    set: (enabled: boolean) => {
        form.value.site_active = !enabled;
    },
});

const loadSettings = async (): Promise<void> => {
    try {
        const response = await adminSettingService.getMaintenance();
        form.value = response.settings;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isLoading.value = false;
    }
};

const saveSettings = async (): Promise<void> => {
    try {
        isSaving.value = true;
        const response = await adminSettingService.updateMaintenance(form.value);
        form.value = response.settings;
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật cài đặt bảo trì.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadSettings);
</script>

<template>
    <div class="mx-auto grid max-w-5xl gap-4">
        <header
            class="flex flex-col gap-3 rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-600">Trạng thái dịch vụ</p>
                <h1 class="mt-1 text-xl font-bold text-slate-950">Bảo trì hệ thống</h1>
                <p class="mt-1 text-sm text-slate-500">Bật hoặc tắt từng phạm vi bảo trì mà không cần triển khai lại website.</p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-[8px] bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="isLoading || isSaving"
                @click="saveSettings"
            >
                <Save class="h-4 w-4" />
                {{ isSaving ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </button>
        </header>

        <div v-if="isLoading" class="grid gap-4">
            <div class="h-40 animate-pulse rounded-[10px] bg-slate-100"></div>
            <div class="h-56 animate-pulse rounded-[10px] bg-slate-100"></div>
        </div>

        <template v-else>
            <article class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-rose-50 text-rose-600"
                            ><Globe2 class="h-5 w-5"
                        /></span>
                        <div>
                            <h2 class="font-bold text-slate-950">Bảo trì website</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                Chuyển toàn bộ khách truy cập sang trang bảo trì. Tài khoản admin và trang đăng nhập admin vẫn hoạt động để có thể tắt
                                chế độ này.
                            </p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input v-model="websiteMaintenanceEnabled" type="checkbox" class="peer sr-only" />
                        <span
                            class="h-6 w-11 rounded-full bg-slate-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:bg-rose-600 peer-checked:after:translate-x-5"
                        ></span>
                    </label>
                </div>
                <div
                    v-if="websiteMaintenanceEnabled"
                    class="mt-4 flex gap-2 rounded-[8px] border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800"
                >
                    <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
                    Sau khi lưu, khách truy cập sẽ được chuyển tới <strong>/bao-tri</strong>.
                </div>
            </article>

            <article class="rounded-[10px] border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-amber-50 text-amber-600"
                            ><Gamepad2 class="h-5 w-5"
                        /></span>
                        <div>
                            <h2 class="font-bold text-slate-950">Bảo trì cổng nạp game</h2>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">
                                Ẩn danh sách gói nạp trên tất cả trang game và chặn tạo đơn mới từ website lẫn API.
                            </p>
                        </div>
                    </div>
                    <label class="relative inline-flex cursor-pointer items-center">
                        <input v-model="form.topup_maintenance_enabled" type="checkbox" class="peer sr-only" />
                        <span
                            class="h-6 w-11 rounded-full bg-slate-200 transition after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:bg-amber-500 peer-checked:after:translate-x-5"
                        ></span>
                    </label>
                </div>

                <label class="mt-5 grid gap-1.5">
                    <span class="text-sm font-semibold text-slate-700">Nội dung thông báo bảo trì</span>
                    <textarea
                        v-model="form.topup_maintenance_message"
                        rows="5"
                        maxlength="2000"
                        class="w-full rounded-[8px] border border-slate-300 px-3 py-2 text-sm leading-6 outline-none transition focus:border-amber-500 focus:ring-2 focus:ring-amber-100 disabled:bg-slate-50 disabled:text-slate-400"
                        :disabled="!form.topup_maintenance_enabled"
                        placeholder="Thông báo hiển thị thay cho danh sách gói nạp..."
                    ></textarea>
                    <span class="text-xs text-slate-500">Nội dung này cũng được trả về khi API tạo đơn bị chặn.</span>
                </label>
            </article>
        </template>
    </div>
</template>
