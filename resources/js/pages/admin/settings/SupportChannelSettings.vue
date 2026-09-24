<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { SupportChannelSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { Headphones, Plus, Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const form = ref<SupportChannelSettingType>({ support_channels: [] });
const loading = ref(true);
const saving = ref(false);

const load = async (): Promise<void> => {
    try {
        const response = await adminSettingService.getSupportChannels();
        form.value.support_channels = Array.isArray(response.settings.support_channels)
            ? response.settings.support_channels.map((channel) => ({
                  icon: String(channel.icon ?? ''),
                  url: String(channel.url ?? ''),
                  is_active: Boolean(channel.is_active ?? true),
              }))
            : [];
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const addChannel = (): void => {
    if (form.value.support_channels.length >= 10) {
        return;
    }

    form.value.support_channels.push({ icon: '', url: '', is_active: true });
};

const removeChannel = (index: number): void => {
    form.value.support_channels.splice(index, 1);
};

const save = async (): Promise<void> => {
    try {
        saving.value = true;
        const response = await adminSettingService.updateSupportChannels({
            support_channels: form.value.support_channels.map((channel) => ({
                icon: channel.icon.trim(),
                url: channel.url.trim(),
                is_active: channel.is_active,
            })),
        });
        form.value.support_channels = response.settings.support_channels;
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật các kênh hỗ trợ.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(load);
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
        <article class="rounded-[10px] border border-slate-200 bg-white p-4">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900">Nút hỗ trợ nổi</h3>
                    <p class="text-sm text-slate-500">
                        Nhập icon và liên kết cho từng kênh. Các mục đang bật sẽ hiện thành danh sách cạnh nút cố định.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-[10px] border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.support_channels.length >= 10"
                        @click="addChannel"
                    >
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Thêm kênh
                    </button>
                    <button
                        type="button"
                        class="rounded-[10px] bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="saving || loading"
                        @click="save"
                    >
                        {{ saving ? 'Đang lưu...' : 'Lưu hỗ trợ' }}
                    </button>
                </div>
            </div>

            <div v-if="loading" class="grid place-items-center py-16 text-sm text-slate-500">Đang tải cấu hình...</div>

            <div v-else-if="form.support_channels.length === 0" class="grid place-items-center gap-3 py-16 text-center">
                <span class="grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-500">
                    <Headphones class="h-6 w-6" aria-hidden="true" />
                </span>
                <div>
                    <p class="font-semibold text-slate-800">Chưa có kênh hỗ trợ</p>
                    <p class="mt-1 text-sm text-slate-500">Nút nổi sẽ được ẩn cho đến khi có ít nhất một kênh đang bật.</p>
                </div>
            </div>

            <div v-else class="grid gap-3 pt-4">
                <div
                    v-for="(channel, index) in form.support_channels"
                    :key="index"
                    class="grid gap-4 rounded-[10px] border border-slate-200 p-4 md:grid-cols-[96px_minmax(0,1fr)_auto]"
                >
                    <div class="grid content-start">
                        <div class="grid h-20 w-20 place-items-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 p-2">
                            <img v-if="channel.icon" :src="channel.icon" alt="" class="h-full w-full object-contain" />
                            <Headphones v-else class="h-7 w-7 text-slate-300" aria-hidden="true" />
                        </div>
                    </div>

                    <div class="grid content-start gap-3">
                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-slate-600">Icon</span>
                            <input
                                v-model="channel.icon"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                placeholder="https://.../icon.png hoặc /storage/..."
                            />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-xs font-semibold text-slate-600">Liên kết</span>
                            <input
                                v-model="channel.url"
                                type="text"
                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2 text-sm outline-none focus:border-indigo-400"
                                placeholder="https://zalo.me/..."
                            />
                        </label>
                        <label class="inline-flex w-fit items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="channel.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600" />
                            Hiển thị trên website
                        </label>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-[10px] border border-rose-200 text-rose-600 transition hover:bg-rose-50"
                        aria-label="Xóa kênh hỗ trợ"
                        @click="removeChannel(index)"
                    >
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </article>

        <aside class="rounded-[10px] border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Xem trước</p>
            <div class="mt-4 flex min-h-72 flex-col items-center justify-end gap-3 rounded-[10px] border border-slate-200 bg-white p-5">
                <template v-for="(channel, index) in form.support_channels" :key="`preview-${index}`">
                    <span
                        v-if="channel.is_active"
                        class="grid h-14 w-14 place-items-center overflow-hidden rounded-full border border-slate-200 bg-white p-2 shadow-lg"
                    >
                        <img v-if="channel.icon" :src="channel.icon" alt="" class="h-full w-full object-contain" />
                        <Headphones v-else class="h-6 w-6 text-slate-300" aria-hidden="true" />
                    </span>
                </template>
                <span class="grid h-16 w-16 place-items-center rounded-full bg-fuchsia-600 text-white shadow-lg">
                    <Headphones class="h-7 w-7" aria-hidden="true" />
                </span>
            </div>
            <p class="mt-3 text-xs leading-5 text-slate-500">Thứ tự trong danh sách cấu hình cũng là thứ tự hiển thị từ trên xuống.</p>
        </aside>
    </div>
</template>
