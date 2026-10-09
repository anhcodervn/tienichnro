<template>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
        <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Thông tin website</h2>
                        <p class="text-sm text-slate-500">Cấu hình chung cho website.</p>
                    </div>
                    <button
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="isSaving"
                        @click="submitForm"
                    >
                        {{ isSaving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </button>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700">Tên website</label>
                        <input
                            v-model="formData.site_name"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-900"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700">Domain chính</label>
                        <input
                            v-model="formData.site_domain"
                            type="text"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-900"
                        />
                    </div>

                    <div class="space-y-2 md:col-span-2">
                        <label class="text-sm font-medium text-slate-700">Mô tả ngắn</label>
                        <textarea
                            v-model="formData.site_description"
                            rows="4"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-slate-900"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-slate-900">Chế độ vận hành</h2>
                    <p class="text-sm text-slate-500">Các thông số ảnh hưởng trực tiếp đến hoạt động của website.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">Website đang hoạt động</p>
                                <p class="mt-1 text-sm text-slate-500">Tắt chế độ bảo trì và cho phép truy cập bình thường.</p>
                            </div>
                            <input v-model="formData.site_active" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300" />
                        </div>
                    </label>

                    <label class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-slate-900">Đăng ký tài khoản mới</p>
                                <p class="mt-1 text-sm text-slate-500">Cho phép người dùng tự tạo tài khoản trên hệ thống.</p>
                            </div>
                            <input v-model="formData.allow_register" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300" />
                        </div>
                    </label>
                </div>
            </div>
        </section>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-slate-900 p-4 text-white shadow-sm">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Health Check</p>

                <div class="mt-4 space-y-3">
                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-sm text-slate-300">Trạng thái hệ thống</p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ formData.site_active ? 'Ổn định' : 'Tạm dừng' }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-white/5 p-3">
                        <p class="text-sm text-slate-300">Domain hiện tại</p>
                        <p class="mt-1 break-all text-lg font-semibold">
                            {{ formData.site_domain || '-' }}
                        </p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>

<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { GeneralSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { onMounted, ref } from 'vue';

const isSaving = ref(false);
const formData = ref<GeneralSettingType>({
    site_name: '',
    site_domain: '',
    site_description: '',
    site_active: true,
    allow_register: false,
    footer_game_links: [],
});

const loadData = async (): Promise<void> => {
    try {
        const data = await adminSettingService.getGeneral();
        formData.value = { ...data.settings };
    } catch (err) {
        handleErrorResponse(err);
    }
};

const submitForm = async (): Promise<void> => {
    try {
        isSaving.value = true;
        const res = await adminSettingService.updateGeneral(formData.value);
        formData.value = { ...res.settings };
        handleSuccessResponse({ data: { status: true, message: 'Cập nhật cài đặt tổng quan thành công' } });
    } catch (err) {
        handleErrorResponse(err);
    } finally {
        isSaving.value = false;
    }
};

onMounted(loadData);
</script>
