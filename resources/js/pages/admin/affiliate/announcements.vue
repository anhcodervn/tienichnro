<script setup lang="ts">
import Editor from '@/components/shared/Editor/index.vue';
import { adminAffiliateService, type AffiliateAnnouncement, type AffiliateAnnouncementData } from '@/services/admin-affiliate.service';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { sanitizeRichText } from '@/utils/rich-text';
import { BellRing, LoaderCircle, Pencil, Pin, PinOff, Plus, Save, Trash2, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { onMounted, reactive, ref, watch } from 'vue';

const data = ref<AffiliateAnnouncementData | null>(null);
const selectedSiteId = ref<number | null>(null);
const editingId = ref<number | null>(null);
const loading = ref(true);
const saving = ref(false);
const form = reactive({ title: '', content: [] as unknown[], is_pinned: false, is_published: true });

const dateTime = (value: string | null): string => (value ? new Date(value).toLocaleString('vi-VN') : 'Chưa đăng');

const resetForm = (): void => {
    editingId.value = null;
    form.title = '';
    form.content = [];
    form.is_pinned = false;
    form.is_published = true;
};

const load = async (): Promise<void> => {
    loading.value = true;
    try {
        data.value = await adminAffiliateService.announcements(selectedSiteId.value || undefined);
        selectedSiteId.value = data.value.selected_site_id;
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
};

const edit = (announcement: AffiliateAnnouncement): void => {
    editingId.value = announcement.id;
    form.title = announcement.title;
    form.content = announcement.content;
    form.is_pinned = announcement.is_pinned;
    form.is_published = announcement.is_published;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const save = async (): Promise<void> => {
    if (!selectedSiteId.value) return;
    saving.value = true;

    try {
        form.content = await uploadEditorImages(form.content);
        const payload = { site_id: selectedSiteId.value, ...form };
        const response = editingId.value
            ? await adminAffiliateService.updateAnnouncement(editingId.value, payload)
            : await adminAffiliateService.createAnnouncement(payload);
        handleSuccessResponse(response, editingId.value ? 'Đã cập nhật thông báo.' : 'Đã gửi thông báo tới cộng tác viên.');
        resetForm();
        await load();
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

const togglePin = async (announcement: AffiliateAnnouncement): Promise<void> => {
    try {
        const response = await adminAffiliateService.updateAnnouncement(announcement.id, {
            site_id: announcement.tenant_id,
            title: announcement.title,
            content: announcement.content,
            is_pinned: !announcement.is_pinned,
            is_published: announcement.is_published,
        });
        handleSuccessResponse(response, announcement.is_pinned ? 'Đã bỏ ghim thông báo.' : 'Đã ghim thông báo lên đầu.');
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

const remove = async (announcement: AffiliateAnnouncement): Promise<void> => {
    const result = await Swal.fire({
        title: 'Xóa thông báo này?',
        text: announcement.title,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xóa thông báo',
        cancelButtonText: 'Hủy',
        confirmButtonColor: '#dc2626',
    });
    if (!result.isConfirmed) return;

    try {
        const response = await adminAffiliateService.deleteAnnouncement(announcement.id);
        handleSuccessResponse(response, 'Đã xóa thông báo.');
        if (editingId.value === announcement.id) resetForm();
        await load();
    } catch (error) {
        handleErrorResponse(error);
    }
};

watch(selectedSiteId, (value, previousValue) => {
    if (previousValue !== null && value !== previousValue) {
        resetForm();
        void load();
    }
});
onMounted(load);
</script>

<template>
    <main class="grid gap-5 p-4 sm:p-6">
        <header class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-blue-700">Affiliate</p>
                <h1 class="mt-1 text-2xl font-black text-slate-950">Thông báo cộng tác viên</h1>
                <p class="mt-1 text-sm text-slate-500">Gửi thông tin tới Partner Center và ghim nội dung quan trọng lên đầu.</p>
            </div>
            <label v-if="data && data.sites.length > 1" class="grid gap-1.5 text-sm font-bold text-slate-700">
                Website
                <select
                    v-model.number="selectedSiteId"
                    class="min-h-11 rounded-xl border-2 border-slate-300 bg-white px-3 focus:border-blue-500 focus:ring-blue-100"
                >
                    <option v-for="site in data.sites" :key="site.id" :value="site.id">{{ site.name }}</option>
                </select>
            </label>
        </header>

        <section class="grid gap-5 xl:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.5fr)] xl:items-start">
            <form class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-20" @submit.prevent="save">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 font-black text-slate-950">
                        <Pencil v-if="editingId" class="size-5 text-blue-600" /><Plus v-else class="size-5 text-blue-600" />
                        {{ editingId ? 'Sửa thông báo' : 'Tạo thông báo' }}
                    </h2>
                    <button
                        v-if="editingId"
                        type="button"
                        class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
                        aria-label="Hủy chỉnh sửa"
                        @click="resetForm"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    Tiêu đề
                    <input
                        v-model.trim="form.title"
                        required
                        maxlength="180"
                        class="min-h-11 rounded-xl border-2 border-slate-300 px-3 focus:border-blue-500 focus:ring-blue-100"
                        placeholder="Nội dung cần cộng tác viên chú ý"
                    />
                </label>
                <div class="grid gap-2 text-sm font-bold text-slate-700">
                    <span>Nội dung</span>
                    <Editor v-model="form.content" :debounce="0" :height="420" />
                    <p class="text-xs font-normal leading-5 text-slate-500">
                        Có thể định dạng văn bản, gắn liên kết và tải ảnh trực tiếp vào nội dung.
                    </p>
                </div>
                <div
                    class="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm font-bold text-slate-700 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"
                >
                    <label class="flex cursor-pointer items-center gap-2"
                        ><input v-model="form.is_published" type="checkbox" class="rounded border-slate-400 text-blue-600 focus:ring-blue-500" /> Hiển
                        thị ngay</label
                    >
                    <label class="flex cursor-pointer items-center gap-2"
                        ><input v-model="form.is_pinned" type="checkbox" class="rounded border-slate-400 text-amber-600 focus:ring-amber-500" /> Ghim
                        lên đầu</label
                    >
                </div>
                <button
                    :disabled="saving"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <LoaderCircle v-if="saving" class="size-5 animate-spin" /><Save v-else class="size-5" />
                    {{ editingId ? 'Lưu thay đổi' : 'Gửi thông báo' }}
                </button>
            </form>

            <div v-if="loading" class="grid min-h-72 place-items-center rounded-2xl border border-slate-200 bg-white">
                <LoaderCircle class="size-8 animate-spin text-blue-600" />
            </div>
            <section v-else class="grid gap-3" aria-label="Danh sách thông báo cộng tác viên">
                <article
                    v-for="announcement in data?.announcements"
                    :key="announcement.id"
                    class="grid gap-4 rounded-2xl border bg-white p-5 shadow-sm"
                    :class="announcement.is_pinned ? 'border-amber-300 ring-1 ring-amber-100' : 'border-slate-200'"
                >
                    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    v-if="announcement.is_pinned"
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800"
                                    ><Pin class="size-3.5" /> Đã ghim</span
                                >
                                <span
                                    class="rounded-full px-2.5 py-1 text-xs font-bold"
                                    :class="announcement.is_published ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                    >{{ announcement.is_published ? 'Đang hiển thị' : 'Bản nháp' }}</span
                                >
                            </div>
                            <h2 class="mt-2 break-words text-lg font-black text-slate-950">{{ announcement.title }}</h2>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ dateTime(announcement.published_at) }} ·
                                {{ announcement.admin?.full_name || announcement.admin?.username || 'Admin' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border border-slate-200 text-amber-700 hover:bg-amber-50"
                                :aria-label="announcement.is_pinned ? 'Bỏ ghim' : 'Ghim thông báo'"
                                @click="togglePin(announcement)"
                            >
                                <PinOff v-if="announcement.is_pinned" class="size-4" /><Pin v-else class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border border-slate-200 text-blue-700 hover:bg-blue-50"
                                aria-label="Sửa thông báo"
                                @click="edit(announcement)"
                            >
                                <Pencil class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="grid size-10 place-items-center rounded-xl border border-slate-200 text-rose-700 hover:bg-rose-50"
                                aria-label="Xóa thông báo"
                                @click="remove(announcement)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </header>
                    <div class="article-content min-w-0 break-words" v-html="sanitizeRichText(announcement.content_html)"></div>
                </article>
                <div
                    v-if="!data?.announcements.length"
                    class="grid min-h-64 place-items-center rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
                >
                    <div>
                        <BellRing class="mx-auto size-9 text-slate-400" />
                        <p class="mt-3 font-bold text-slate-700">Chưa có thông báo</p>
                        <p class="mt-1 text-sm text-slate-500">Tạo thông báo đầu tiên để gửi tới cộng tác viên.</p>
                    </div>
                </div>
            </section>
        </section>
    </main>
</template>
