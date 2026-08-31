<script setup lang="ts">
import { adminSettingService } from '@/services/admin-setting.service';
import type { BioLinkItemType, BioSettingType } from '@/types/setting.type';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { ArrowDown, ArrowUp, ExternalLink, Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const isLoading = ref(true);
const isSaving = ref(false);
const form = ref<BioSettingType>({
    bio_title: '',
    bio_description: '',
    bio_avatar_url: '',
    bio_links: [],
});

const activeLinks = computed(() => form.value.bio_links.filter((link) => link.is_active));

const createLink = (): BioLinkItemType => ({
    label: '',
    url: '',
    is_active: true,
});

const loadSettings = async (): Promise<void> => {
    try {
        const response = await adminSettingService.getBio();
        form.value = {
            ...response.settings,
            bio_links: Array.isArray(response.settings.bio_links) ? response.settings.bio_links : [],
        };
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isLoading.value = false;
    }
};

const saveSettings = async (): Promise<void> => {
    try {
        isSaving.value = true;
        const response = await adminSettingService.updateBio(form.value);
        form.value = { ...response.settings, bio_links: response.settings.bio_links ?? [] };
        handleSuccessResponse({ data: { status: true, message: 'Đã cập nhật trang Bio.' } });
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        isSaving.value = false;
    }
};

const addLink = (): void => {
    form.value.bio_links.push(createLink());
};

const removeLink = (index: number): void => {
    form.value.bio_links.splice(index, 1);
};

const moveLink = (index: number, direction: -1 | 1): void => {
    const destination = index + direction;

    if (destination < 0 || destination >= form.value.bio_links.length) {
        return;
    }

    const [link] = form.value.bio_links.splice(index, 1);
    form.value.bio_links.splice(destination, 0, link);
};

onMounted(loadSettings);
</script>

<template>
    <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="grid gap-4">
            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-600">Link in bio</p>
                        <h1 class="mt-1 text-xl font-bold text-slate-950">Cấu hình trang Bio</h1>
                        <p class="mt-1 text-sm text-slate-500">Tùy chỉnh nội dung hiển thị tại đường dẫn /comutry.</p>
                    </div>
                    <div class="flex gap-2">
                        <a
                            class="inline-flex min-h-10 items-center gap-2 rounded-[8px] border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                            href="/comutry"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink class="h-4 w-4" />
                            Xem trang
                        </a>
                        <button
                            type="button"
                            class="min-h-10 rounded-[8px] bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="isLoading || isSaving"
                            @click="saveSettings"
                        >
                            {{ isSaving ? 'Đang lưu...' : 'Lưu thay đổi' }}
                        </button>
                    </div>
                </div>

                <div v-if="isLoading" class="mt-5 grid gap-3">
                    <div class="h-11 animate-pulse rounded-[8px] bg-slate-100"></div>
                    <div class="h-24 animate-pulse rounded-[8px] bg-slate-100"></div>
                </div>

                <div v-else class="mt-5 grid gap-4">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold text-slate-700">Tên hiển thị</span>
                        <input
                            v-model="form.bio_title"
                            type="text"
                            maxlength="120"
                            class="min-h-11 w-full rounded-[8px] border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="Nạp Carot"
                        />
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold text-slate-700">Mô tả ngắn</span>
                        <textarea
                            v-model="form.bio_description"
                            rows="3"
                            maxlength="500"
                            class="w-full rounded-[8px] border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="Mô tả thương hiệu hoặc lời chào ngắn..."
                        ></textarea>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-semibold text-slate-700">URL ảnh đại diện</span>
                        <input
                            v-model="form.bio_avatar_url"
                            type="text"
                            class="min-h-11 w-full rounded-[8px] border border-slate-300 px-3 py-2 text-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="https://... hoặc /storage/..."
                        />
                        <span class="text-xs text-slate-500">Để trống sẽ dùng logo website.</span>
                    </label>
                </div>
            </article>

            <article class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-950">Danh sách liên kết</h2>
                        <p class="mt-1 text-sm text-slate-500">Thứ tự bên dưới cũng là thứ tự hiển thị ngoài trang Bio.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-[8px] bg-emerald-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        :disabled="form.bio_links.length >= 20"
                        @click="addLink"
                    >
                        <Plus class="h-4 w-4" />
                        Thêm link
                    </button>
                </div>

                <div class="mt-4 grid gap-3">
                    <div v-for="(link, index) in form.bio_links" :key="index" class="rounded-[10px] border border-slate-200 bg-slate-50 p-3">
                        <div class="grid gap-3 md:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)_auto]">
                            <label class="grid gap-1">
                                <span class="text-xs font-semibold text-slate-600">Tên link</span>
                                <input
                                    v-model="link.label"
                                    type="text"
                                    maxlength="80"
                                    class="min-h-10 w-full rounded-[8px] border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-500"
                                    placeholder="Facebook"
                                />
                            </label>
                            <label class="grid gap-1">
                                <span class="text-xs font-semibold text-slate-600">URL</span>
                                <input
                                    v-model="link.url"
                                    type="text"
                                    class="min-h-10 w-full rounded-[8px] border border-slate-300 bg-white px-3 text-sm outline-none focus:border-emerald-500"
                                    placeholder="https://..."
                                />
                            </label>
                            <div class="flex items-end gap-1">
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-[8px] border border-slate-200 bg-white text-slate-600 disabled:opacity-30"
                                    :disabled="index === 0"
                                    aria-label="Đưa link lên"
                                    @click="moveLink(index, -1)"
                                >
                                    <ArrowUp class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-[8px] border border-slate-200 bg-white text-slate-600 disabled:opacity-30"
                                    :disabled="index === form.bio_links.length - 1"
                                    aria-label="Đưa link xuống"
                                    @click="moveLink(index, 1)"
                                >
                                    <ArrowDown class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="grid h-10 w-10 place-items-center rounded-[8px] border border-rose-200 bg-white text-rose-600 transition hover:bg-rose-50"
                                    aria-label="Xóa link"
                                    @click="removeLink(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                        <label
                            class="mt-3 flex items-center justify-between gap-3 rounded-[8px] border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700"
                        >
                            Hiển thị liên kết này
                            <input
                                v-model="link.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                        </label>
                    </div>

                    <div
                        v-if="form.bio_links.length === 0"
                        class="rounded-[10px] border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500"
                    >
                        Chưa có liên kết. Nhấn “Thêm link” để bắt đầu.
                    </div>
                </div>
            </article>
        </section>

        <aside class="xl:sticky xl:top-4 xl:self-start">
            <div class="overflow-hidden rounded-[18px] border border-slate-800 bg-slate-950 p-5 text-white shadow-xl">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">Xem trước</p>
                <div class="mt-6 flex flex-col items-center text-center">
                    <img
                        v-if="form.bio_avatar_url"
                        :src="form.bio_avatar_url"
                        alt="Ảnh đại diện xem trước"
                        class="h-20 w-20 rounded-full border-4 border-white/10 bg-white object-cover"
                    />
                    <div
                        v-else
                        class="grid h-20 w-20 place-items-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 text-2xl font-extrabold text-slate-950"
                    >
                        {{ (form.bio_title || 'B').slice(0, 1).toUpperCase() }}
                    </div>
                    <h2 class="mt-4 text-xl font-extrabold">{{ form.bio_title || 'Tên hiển thị' }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">{{ form.bio_description || 'Mô tả ngắn sẽ xuất hiện tại đây.' }}</p>
                </div>
                <div class="mt-6 grid gap-2">
                    <div
                        v-for="(link, index) in activeLinks"
                        :key="index"
                        class="rounded-xl border border-white/10 bg-white/[0.07] px-4 py-3 text-sm font-semibold"
                    >
                        {{ link.label || 'Tên liên kết' }}
                    </div>
                    <p
                        v-if="activeLinks.length === 0"
                        class="rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-xs text-slate-500"
                    >
                        Chưa có link đang bật.
                    </p>
                </div>
            </div>
        </aside>
    </div>
</template>
