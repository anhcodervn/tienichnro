<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminSeoPostItem, AdminSeoPostPayload, SeoPostRevisionSummary } from '@/types/admin-seo.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleSuccessResponse } from '@/utils/response';
import { Dialog, DialogDescription, DialogPanel, DialogTitle } from '@headlessui/vue';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { newSeoPostForm, seoPostFormFromSnapshot } from './form';

const route = useRoute();
const router = useRouter();
const editingId = computed(() => Number(route.params.seo_post_id) || null);
const saving = ref(false);
const loading = ref(false);
const failed = ref(false);
const restoring = ref(false);
const errorMessage = ref('');
const notice = ref('');
const currentVersion = ref(0);
const editorKey = ref(0);
const coverKey = ref(0);
const slugManuallyEdited = ref(false);
const canonicalMode = ref<'auto' | 'custom'>('auto');
const contentEditor = ref<{ flush: () => unknown[] | string; isUploadingImage: boolean } | null>(null);
const coverUploader = ref<{ loading: boolean } | null>(null);
const categories = ref<Array<{ id: number; name: string; slug: string }>>([]);
const form = reactive<AdminSeoPostPayload>(newSeoPostForm());
const historyOpen = ref(false);
const historyLoading = ref(false);
const historyError = ref('');
const revisions = ref<SeoPostRevisionSummary[]>([]);
const busy = computed(
    () => saving.value || loading.value || restoring.value || Boolean(contentEditor.value?.isUploadingImage) || Boolean(coverUploader.value?.loading),
);
const pageTitle = computed(() => (editingId.value ? 'Cập nhật bài viết SEO' : 'Tạo bài viết SEO'));
const selectedCategorySlug = computed(() => categories.value.find((category) => category.id === form.seo_category_id)?.slug);
const publicUrl = computed(() => `${window.location.origin}/${selectedCategorySlug.value || 'bai-viet'}/${form.slug || 'duong-dan-bai-viet'}`);
const generatedCanonicalUrl = computed(() => publicUrl.value);
const seoTitlePreview = computed(() => form.seo_title?.trim() || form.title || 'Tiêu đề bài viết');
const seoDescriptionPreview = computed(() => form.seo_description?.trim() || form.excerpt || 'Mô tả bài viết trên kết quả tìm kiếm.');
const inputClass =
    'min-h-11 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:focus:ring-emerald-900';
const cardClass = 'min-w-0 rounded-xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-700 dark:bg-slate-900';
const errorText = (error: unknown): string => {
    const value = error as { response?: { data?: { message?: string } }; message?: string };
    return value.response?.data?.message || value.message || 'Không thể thực hiện thao tác. Vui lòng thử lại.';
};
const slugify = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
const handleTitleInput = (): void => {
    if (!editingId.value && !slugManuallyEdited.value) form.slug = slugify(form.seo_title || '');
};
const applySnapshot = (snapshot: Partial<AdminSeoPostPayload>): void => {
    Object.assign(form, seoPostFormFromSnapshot(snapshot));
    canonicalMode.value = form.canonical_url ? 'custom' : 'auto';
    slugManuallyEdited.value = true;
    editorKey.value++;
    coverKey.value++;
};
let loadSequence = 0;
const load = async (): Promise<void> => {
    const sequence = ++loadSequence;
    const id = editingId.value;
    loading.value = true;
    failed.value = false;
    errorMessage.value = '';
    notice.value = '';
    historyOpen.value = false;
    try {
        const [options, post] = await Promise.all([adminSeoService.postOptions(), id ? adminSeoService.getPost(id) : Promise.resolve(null)]);
        if (sequence !== loadSequence) return;
        categories.value = options.categories;
        if (post) {
            applySnapshot(post);
            currentVersion.value = post.lock_version;
        } else {
            Object.assign(form, newSeoPostForm());
            currentVersion.value = 0;
            canonicalMode.value = 'auto';
            slugManuallyEdited.value = false;
            editorKey.value++;
            coverKey.value++;
        }
    } catch (error) {
        if (sequence !== loadSequence) return;
        failed.value = true;
        errorMessage.value = errorText(error);
    } finally {
        if (sequence === loadSequence) loading.value = false;
    }
};
const handleSave = async (): Promise<void> => {
    if (busy.value || failed.value) return;
    saving.value = true;
    errorMessage.value = '';
    try {
        const latestContent = contentEditor.value?.flush();
        if (Array.isArray(latestContent)) form.content = latestContent;
        await nextTick();
        form.content = await uploadEditorImages(form.content ?? []);
        const payload: AdminSeoPostPayload = {
            ...form,
            slug: slugify(form.slug),
            canonical_url: canonicalMode.value === 'custom' ? form.canonical_url?.trim() || null : null,
            status: 'published',
            published_at: null,
            scheduled_at: null,
            faq:
                form.faq === null
                    ? null
                    : (form.faq ?? [])
                          .map((item) => ({ question: item.question.trim(), answer: item.answer.trim() }))
                          .filter((item) => item.question || item.answer),
            ...(editingId.value ? { expected_version: currentVersion.value } : {}),
        };
        const response = editingId.value ? await adminSeoService.updatePost(editingId.value, payload) : await adminSeoService.createPost(payload);
        const post = response.data.data as AdminSeoPostItem;
        applySnapshot(post);
        currentVersion.value = post.lock_version;
        notice.value = '';
        handleSuccessResponse(response);
        if (!editingId.value) await router.replace(`/admin/seo/posts/${post.id}/edit`);
    } catch (error) {
        errorMessage.value = errorText(error);
    } finally {
        saving.value = false;
    }
};
const loadHistory = async (): Promise<void> => {
    if (busy.value) return;
    historyOpen.value = true;
    historyLoading.value = true;
    historyError.value = '';
    revisions.value = [];
    try {
        if (editingId.value) revisions.value = await adminSeoService.revisions(editingId.value);
    } catch (error) {
        historyError.value = errorText(error);
    } finally {
        historyLoading.value = false;
    }
};
const restoreRevision = async (revisionId: number): Promise<void> => {
    if (!editingId.value || busy.value || historyLoading.value) return;
    restoring.value = true;
    historyError.value = '';
    try {
        const revision = await adminSeoService.revision(editingId.value, revisionId);
        applySnapshot(revision.snapshot);
        notice.value = 'Đã lấy dữ liệu SEO cũ vào form. Bạn có thể chỉnh sửa tiếp; cần bấm Lưu bài viết để xuất bản.';
        errorMessage.value = '';
        historyOpen.value = false;
    } catch (error) {
        historyError.value = errorText(error);
    } finally {
        restoring.value = false;
    }
};
const closeHistory = (): void => {
    if (!restoring.value) historyOpen.value = false;
};
const clearCoverImage = (): void => {
    form.cover_image = null;
    form.cover_alt = null;
    coverKey.value++;
};
const addFaq = (): void => {
    form.faq ??= [];
    form.faq.push({ question: '', answer: '' });
};
const date = (value: string): string => new Date(value).toLocaleString('vi-VN');
watch(editingId, load, { immediate: true });
</script>

<template>
    <div class="grid gap-4 text-slate-900 dark:text-slate-100">
        <button
            type="button"
            class="min-h-11 w-fit rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold disabled:opacity-50 dark:border-slate-600 dark:bg-slate-900"
            :disabled="busy"
            @click="loadHistory"
        >
            Bản SEO cũ
        </button>
        <Breadcrumb :title="pageTitle" description="Soạn nội dung và lưu để xuất bản ngay.">
            <template #actions
                ><div class="flex gap-2">
                    <RouterLink class="text-sm font-semibold text-emerald-700 dark:text-emerald-400" to="/admin/seo/posts">Danh sách</RouterLink
                    ><a v-if="editingId" class="text-sm text-slate-500" :href="publicUrl" target="_blank" rel="noreferrer">Xem bài viết</a>
                </div></template
            >
        </Breadcrumb>
        <p v-if="loading" role="status" :class="cardClass">Đang tải bài viết…</p>
        <p
            v-if="notice"
            role="status"
            class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ notice }}
        </p>
        <div
            v-if="errorMessage"
            role="alert"
            class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300"
        >
            <p>{{ errorMessage }}</p>
            <button v-if="failed" type="button" class="mt-2 min-h-11 font-bold underline" @click="load">Tải lại</button>
        </div>
        <form v-if="!loading && !failed" @submit.prevent="handleSave">
            <fieldset :disabled="busy" :inert="busy ? true : undefined" class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
                <section class="grid min-w-0 gap-4" aria-label="Soạn bài SEO">
                    <article :class="cardClass">
                        <h2 class="mb-4 text-base font-bold">Thông tin bài viết</h2>
                        <div class="grid gap-4">
                            <label class="grid gap-2 text-sm font-semibold"
                                >SEO title<input v-model="form.seo_title" :class="inputClass" maxlength="255" @input="handleTitleInput"
                            /></label>
                            <label class="grid gap-2 text-sm font-semibold"
                                >SEO description<textarea v-model="form.seo_description" :class="inputClass" rows="3" maxlength="320"></textarea>
                            </label>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="grid gap-2 text-sm font-semibold"
                                    >Keyword<input v-model="form.focus_keyword" :class="inputClass" maxlength="255" /></label
                                ><label class="grid gap-2 text-sm font-semibold"
                                    >Meta keywords<input v-model="form.meta_keywords" :class="inputClass" maxlength="1000"
                                /></label>
                            </div>
                            <label class="grid gap-2 text-sm font-semibold"
                                >Đường dẫn bài viết (slug)<input
                                    v-model="form.slug"
                                    :class="inputClass"
                                    required
                                    maxlength="255"
                                    pattern="[a-z0-9]+(-[a-z0-9]+)*"
                                    @input="slugManuallyEdited = true"
                                /><span class="break-all text-xs font-normal text-slate-500">Preview URL: {{ publicUrl }}</span></label
                            >
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="grid gap-2 text-sm font-semibold"
                                    >Danh mục<select v-model="form.seo_category_id" :class="inputClass" required>
                                        <option :value="null" disabled>Chọn danh mục</option>
                                        <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                    </select></label
                                >
                                <label class="grid gap-2 text-sm font-semibold"
                                    >Ngôn ngữ bài viết<input
                                        v-model="form.language"
                                        :class="inputClass"
                                        required
                                        maxlength="35"
                                        list="seo-languages"
                                        placeholder="vi hoặc en"
                                    /><datalist id="seo-languages">
                                        <option value="vi">Tiếng Việt</option>
                                        <option value="en">English</option>
                                    </datalist></label
                                >
                            </div>
                            <label class="grid gap-2 text-sm font-semibold"
                                >Loại bài viết<select v-model="form.type" :class="inputClass">
                                    <option value="knowledge">Kiến thức</option>
                                    <option value="guide">Hướng dẫn</option>
                                </select></label
                            >
                        </div>
                    </article>
                    <article :class="cardClass" aria-labelledby="seo-content-title">
                        <h2 id="seo-content-title" class="text-base font-bold">Nội dung bài viết</h2>
                        <p class="mb-4 mt-2 text-sm text-slate-500">Viết H1 và đoạn giới thiệu trực tiếp trong nội dung bên dưới.</p>
                        <Editor :key="editorKey" ref="contentEditor" :model-value="form.content ?? []" @update:model-value="form.content = $event" />
                    </article>
                    <article :class="cardClass" aria-labelledby="seo-faq-title">
                        <h2 id="seo-faq-title" class="text-base font-bold">Câu hỏi thường gặp (FAQ)</h2>
                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="(item, index) in form.faq ?? []"
                                :key="index"
                                class="grid gap-2 rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <label class="grid gap-1 text-sm">Câu hỏi<input v-model="item.question" :class="inputClass" maxlength="255" /></label
                                ><label class="grid gap-1 text-sm"
                                    >Trả lời<textarea v-model="item.answer" :class="inputClass" rows="2" maxlength="2000"></textarea></label
                                ><button type="button" class="min-h-11 text-sm text-rose-600" @click="form.faq?.splice(index, 1)">Xóa câu hỏi</button>
                            </div>
                            <button
                                type="button"
                                class="min-h-11 text-sm font-semibold text-emerald-700 dark:text-emerald-400"
                                :disabled="(form.faq?.length ?? 0) >= 20"
                                @click="addFaq"
                            >
                                Thêm câu hỏi
                            </button>
                        </div>
                    </article>
                </section>
                <aside class="grid min-w-0 gap-4" aria-label="SEO và ảnh đại diện">
                    <article :class="cardClass">
                        <h2 class="mb-4 text-base font-bold">Hiển thị trên Google</h2>
                        <div class="mb-4 min-w-0 rounded-lg border border-slate-200 p-3 dark:border-slate-700" aria-label="Xem trước kết quả Google">
                            <p class="break-all text-xs text-slate-500">{{ publicUrl }}</p>
                            <p class="mt-2 break-words text-lg text-blue-700 dark:text-blue-400">{{ seoTitlePreview }}</p>
                            <p class="mt-1 break-words text-sm text-slate-600 dark:text-slate-300">{{ seoDescriptionPreview }}</p>
                        </div>
                        <div class="grid gap-4">
                            <label class="grid gap-2 text-sm font-semibold"
                                >Canonical URL<select v-model="canonicalMode" :class="inputClass">
                                    <option value="auto">Canonical tự động</option>
                                    <option value="custom">Canonical tùy chỉnh</option>
                                </select></label
                            >
                            <p
                                v-if="canonicalMode === 'auto'"
                                class="break-all rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ generatedCanonicalUrl }}
                            </p>
                            <label v-else class="grid gap-2 text-sm"
                                >URL canonical tùy chỉnh<input
                                    v-model="form.canonical_url"
                                    type="url"
                                    :class="inputClass"
                                    maxlength="2048"
                                    placeholder="https://..."
                            /></label>
                            <label class="grid gap-2 text-sm font-semibold"
                                >Cho phép lập chỉ mục<select v-model="form.robots" :class="inputClass">
                                    <option value="index,follow">index,follow</option>
                                    <option value="noindex,follow">noindex,follow</option>
                                </select></label
                            >
                            <label class="flex items-center gap-2 text-sm"
                                ><input v-model="form.article_schema" type="checkbox" class="rounded border-slate-300 text-emerald-600" />Schema
                                Article</label
                            >
                            <label class="flex items-center gap-2 text-sm"
                                ><input v-model="form.breadcrumb_schema" type="checkbox" class="rounded border-slate-300 text-emerald-600" />Schema
                                Breadcrumb</label
                            >
                            <button
                                type="submit"
                                :disabled="busy"
                                class="min-h-11 w-full rounded-lg bg-emerald-700 px-4 py-3 text-sm font-bold text-white disabled:opacity-50"
                            >
                                {{ saving ? 'Đang lưu…' : 'Lưu bài viết' }}
                            </button>
                            <p class="text-xs text-slate-500">Lưu để xuất bản ngay. noindex chỉ tắt lập chỉ mục tìm kiếm.</p>
                        </div>
                    </article>
                    <article :class="cardClass">
                        <h2 class="mb-4 text-base font-bold">Ảnh đại diện SEO</h2>
                        <UploadImage
                            :key="coverKey"
                            ref="coverUploader"
                            :image-src="form.cover_image"
                            :disabled="busy"
                            :compress="true"
                            @uploaded="form.cover_image = $event"
                        />
                        <button v-if="form.cover_image" type="button" class="mt-2 min-h-11 text-sm text-rose-600" @click="clearCoverImage">
                            Xóa ảnh
                        </button>
                        <label class="mt-4 grid gap-2 text-sm font-semibold"
                            >Mô tả ảnh (alt)<input v-model="form.cover_alt" :class="inputClass" :required="Boolean(form.cover_image)" maxlength="255"
                        /></label>
                    </article>
                </aside>
            </fieldset>
        </form>
        <Dialog :open="historyOpen" class="relative z-[70]" @close="closeHistory">
            <div class="fixed inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="fixed inset-0 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-3 sm:p-6">
                    <DialogPanel
                        class="w-full max-w-3xl rounded-xl bg-white p-4 text-slate-900 shadow-xl dark:bg-slate-900 dark:text-slate-100 sm:p-6"
                    >
                        <header class="flex items-start justify-between gap-4">
                            <div>
                                <DialogTitle class="text-lg font-bold">Bản SEO cũ</DialogTitle
                                ><DialogDescription class="mt-1 text-sm text-slate-500"
                                    >Tối đa 10 phiên bản đã xuất bản trước bản hiện tại. Lấy dữ liệu chỉ điền vào form.</DialogDescription
                                >
                            </div>
                            <button
                                type="button"
                                class="min-h-11 shrink-0 px-3 text-sm font-semibold disabled:opacity-50"
                                :disabled="restoring"
                                @click="closeHistory"
                            >
                                Đóng
                            </button>
                        </header>
                        <p v-if="historyLoading" class="py-8 text-sm" role="status">Đang tải lịch sử…</p>
                        <div v-else-if="historyError" class="py-4 text-sm text-rose-600" role="alert">
                            <p>{{ historyError }}</p>
                            <button type="button" class="min-h-11 font-bold underline" :disabled="restoring" @click="loadHistory">Thử lại</button>
                        </div>
                        <p v-else-if="!revisions.length" class="py-8 text-center text-sm text-slate-500">Chưa có bản SEO cũ.</p>
                        <div v-else class="mt-4 grid max-h-[65vh] gap-3 overflow-y-auto">
                            <article
                                v-for="revision in revisions"
                                :key="revision.id"
                                class="grid gap-2 rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <time class="text-xs text-slate-500">{{ date(revision.published_at || revision.created_at) }}</time>
                                <h3 class="break-words text-sm font-bold">{{ revision.seo_title || revision.title || 'Chưa có SEO title' }}</h3>
                                <p class="break-words text-sm text-slate-500">{{ revision.seo_description || 'Chưa có mô tả SEO' }}</p>
                                <p class="break-all text-xs text-slate-500">Slug: {{ revision.slug }}</p>
                                <button
                                    type="button"
                                    class="min-h-11 w-fit rounded-lg bg-emerald-700 px-4 text-sm font-semibold text-white disabled:opacity-50"
                                    :disabled="busy"
                                    @click="restoreRevision(revision.id)"
                                >
                                    {{ restoring ? 'Đang lấy dữ liệu…' : 'Lấy dữ liệu' }}
                                </button>
                            </article>
                        </div>
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    </div>
</template>
