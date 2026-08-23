<script setup lang="ts">
import Breadcrumb from '@/components/MasterLayouts/Breadcrumb/index.vue';
import Editor from '@/components/shared/Editor/index.vue';
import UploadImage from '@/components/shared/UpladImage/index.vue';
import { adminSeoService } from '@/services/admin-seo.service';
import type { AdminSeoPostPayload, SeoPostStatus, SeoRobotsValue } from '@/types/admin-seo.type';
import { uploadEditorImages } from '@/utils/editor-image-upload';
import { handleErrorResponse, handleSuccessResponse } from '@/utils/response';
import { ArrowLeft, CheckCircle2, CircleAlert, ExternalLink, Eye, ImageIcon, Link2, Save, Search, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const saving = ref(false);
const loading = ref(false);
const slugManuallyEdited = ref(false);
const canonicalMode = ref<'auto' | 'custom'>('auto');
const categories = ref<Array<{ id: number; name: string; slug: string }>>([]);

const editingId = computed(() => {
    const raw = Number(route.params.seo_post_id);
    return Number.isInteger(raw) && raw > 0 ? raw : null;
});

const form = reactive<AdminSeoPostPayload>({
    title: '',
    slug: '',
    seo_category_id: null,
    excerpt: '',
    content: [],
    cover_image: null,
    cover_alt: '',
    seo_title: '',
    seo_description: '',
    canonical_url: null,
    robots: 'index,follow',
    focus_keyword: '',
    article_schema: true,
    breadcrumb_schema: true,
    status: 'draft',
    published_at: null,
    scheduled_at: null,
});

const pageTitle = computed(() => (editingId.value ? 'Cập nhật bài viết SEO' : 'Tạo bài viết SEO'));
const selectedCategorySlug = computed(
    () => categories.value.find((category) => category.id === form.seo_category_id)?.slug || 'danh-muc',
);
const publicUrl = computed(
    () => `${window.location.origin}/${selectedCategorySlug.value}/${form.slug.trim() || 'duong-dan-bai-viet'}`,
);
const generatedCanonicalUrl = computed(() => publicUrl.value);
const effectiveCanonicalUrl = computed(() =>
    canonicalMode.value === 'custom' && form.canonical_url?.trim() ? form.canonical_url.trim() : generatedCanonicalUrl.value,
);
const seoTitlePreview = computed(() => form.seo_title?.trim() || form.title.trim() || 'Tiêu đề bài viết');
const seoDescriptionPreview = computed(
    () => form.seo_description?.trim() || form.excerpt?.trim() || 'Mô tả bài viết sẽ xuất hiện trên kết quả tìm kiếm.',
);
const canonicalUsesExternalDomain = computed(() => {
    if (canonicalMode.value !== 'custom' || !form.canonical_url?.trim()) {
        return false;
    }

    try {
        return new URL(form.canonical_url).origin !== window.location.origin;
    } catch {
        return false;
    }
});

const seoChecks = computed(() => [
    {
        label: 'SEO title từ 30–65 ký tự',
        passed: seoTitlePreview.value.length >= 30 && seoTitlePreview.value.length <= 65,
    },
    {
        label: 'SEO description từ 120–160 ký tự',
        passed: seoDescriptionPreview.value.length >= 120 && seoDescriptionPreview.value.length <= 160,
    },
    {
        label: 'Có focus keyword',
        passed: Boolean(form.focus_keyword?.trim()),
    },
    {
        label: 'Ảnh đại diện có alt text',
        passed: Boolean(form.cover_image && form.cover_alt?.trim()),
    },
    {
        label: 'Nội dung bài viết đã được soạn',
        passed: Array.isArray(form.content) && form.content.length > 0,
    },
    {
        label: 'Canonical hợp lệ',
        passed: Boolean(effectiveCanonicalUrl.value),
    },
]);
const passedSeoChecks = computed(() => seoChecks.value.filter((item) => item.passed).length);

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
    if (!editingId.value && !slugManuallyEdited.value) {
        form.slug = slugify(form.title);
    }
};

const markSlugAsEdited = (): void => {
    slugManuallyEdited.value = true;
    form.slug = slugify(form.slug);
};

const toLocalDatetime = (value: string | null): string | null => {
    if (!value) {
        return null;
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60_000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};

const fetchMeta = async (): Promise<void> => {
    const response = await adminSeoService.listCategories();
    categories.value = response.map((category) => ({ id: category.id, name: category.name, slug: category.slug }));
};

const fetchPost = async (): Promise<void> => {
    if (!editingId.value) {
        return;
    }

    const post = await adminSeoService.getPost(editingId.value);

    form.title = post.title;
    form.slug = post.slug;
    form.seo_category_id = post.seo_category_id;
    form.excerpt = post.excerpt ?? '';
    form.content = Array.isArray(post.content) ? post.content : [];
    form.cover_image = post.cover_image ?? null;
    form.cover_alt = post.cover_alt ?? '';
    form.seo_title = post.seo_title ?? '';
    form.seo_description = post.seo_description ?? '';
    form.canonical_url = post.canonical_url ?? null;
    form.robots = post.robots as SeoRobotsValue;
    form.focus_keyword = post.focus_keyword ?? '';
    form.article_schema = post.article_schema;
    form.breadcrumb_schema = post.breadcrumb_schema;
    form.status = post.status as SeoPostStatus;
    form.published_at = toLocalDatetime(post.published_at);
    form.scheduled_at = toLocalDatetime(post.scheduled_at);
    canonicalMode.value = post.canonical_url ? 'custom' : 'auto';
    slugManuallyEdited.value = true;
};

const clearCoverImage = (): void => {
    form.cover_image = null;
    form.cover_alt = '';
};

const handleSave = async (): Promise<void> => {
    saving.value = true;

    try {
        form.content = await uploadEditorImages(form.content ?? []);

        const payload: AdminSeoPostPayload = {
            ...form,
            title: form.title.trim(),
            slug: slugify(form.slug),
            excerpt: form.excerpt?.trim() ?? '',
            cover_alt: form.cover_alt?.trim() ?? '',
            seo_title: form.seo_title?.trim() ?? '',
            seo_description: form.seo_description?.trim() ?? '',
            focus_keyword: form.focus_keyword?.trim() ?? '',
            canonical_url: canonicalMode.value === 'custom' ? form.canonical_url?.trim() || null : null,
            content: form.content ?? [],
            published_at: form.status === 'published' ? form.published_at : null,
            scheduled_at: form.status === 'scheduled' ? form.scheduled_at : null,
        };

        const response = editingId.value ? await adminSeoService.updatePost(editingId.value, payload) : await adminSeoService.createPost(payload);

        handleSuccessResponse(response);

        if (!editingId.value) {
            await router.push('/admin/seo/posts');
        }
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    try {
        loading.value = true;
        await Promise.all([fetchMeta(), fetchPost()]);
    } catch (error) {
        handleErrorResponse(error);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="space-y-4">
        <Breadcrumb :title="pageTitle" description="Quản lý nội dung, ảnh đại diện, hiển thị tìm kiếm và thiết lập xuất bản trong một luồng rõ ràng.">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <RouterLink
                        to="/admin/seo/posts"
                        class="inline-flex items-center gap-2 rounded-[10px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Danh sách
                    </RouterLink>
                    <a
                        v-if="editingId && form.slug"
                        :href="publicUrl"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-2 rounded-[10px] border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        <Eye class="h-4 w-4" />
                        Xem bài viết
                    </a>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-[10px] bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="saving || loading"
                        @click="handleSave"
                    >
                        <Save class="h-4 w-4" />
                        {{ saving ? 'Đang lưu...' : editingId ? 'Lưu thay đổi' : 'Tạo bài viết' }}
                    </button>
                </div>
            </template>
        </Breadcrumb>

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_390px]">
            <section class="grid min-w-0 gap-4">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-semibold text-slate-950">Nội dung bài viết</h2>
                        <p class="mt-1 text-sm text-slate-500">Nhập tiêu đề trước, slug sẽ được tạo tự động và vẫn có thể chỉnh tay.</p>
                    </div>

                    <div class="grid gap-4 pt-5 md:grid-cols-2">
                        <label class="grid gap-2 md:col-span-2">
                            <span class="text-sm font-semibold text-slate-700">Tiêu đề bài viết <span class="text-rose-500">*</span></span>
                            <input
                                v-model="form.title"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Ví dụ: Hướng dẫn nạp Ngọc Rồng Online nhanh và an toàn"
                                @input="handleTitleInput"
                            />
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Slug URL <span class="text-rose-500">*</span></span>
                            <input
                                v-model="form.slug"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2.5 font-mono text-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="huong-dan-nap-ngoc-rong"
                                @change="markSlugAsEdited"
                            />
                            <span class="truncate text-xs text-slate-400">/{{ selectedCategorySlug }}/{{ form.slug || 'duong-dan-bai-viet' }}</span>
                        </label>

                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Danh mục <span v-if="form.status !== 'draft'" class="text-rose-500">*</span></span>
                            <select
                                v-model="form.seo_category_id"
                                class="w-full rounded-[10px] border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                            >
                                <option :value="null">Không gắn danh mục (chỉ bản nháp)</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </label>

                        <label class="grid gap-2 md:col-span-2">
                            <span class="flex items-center justify-between gap-3 text-sm font-semibold text-slate-700">
                                <span>Mô tả ngắn</span>
                                <span class="text-xs font-normal text-slate-400">{{ (form.excerpt ?? '').length }} ký tự</span>
                            </span>
                            <textarea
                                v-model="form.excerpt"
                                rows="3"
                                class="w-full resize-y rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Tóm tắt nội dung để hiển thị ở danh sách bài viết..."
                            />
                        </label>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-semibold text-slate-950">
                                <ImageIcon class="h-5 w-5 text-violet-600" />
                                Ảnh đại diện
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Upload trực tiếp, hệ thống tối ưu WebP và dùng ảnh này cho trang bài viết lẫn chia sẻ mạng xã hội.
                            </p>
                        </div>
                        <button
                            v-if="form.cover_image"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-[8px] border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-50"
                            @click="clearCoverImage"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Xóa ảnh
                        </button>
                    </div>

                    <div class="grid gap-4 pt-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.8fr)]">
                        <UploadImage
                            :accept="['image/jpeg', 'image/png', 'image/webp']"
                            :compress="true"
                            :image-src="form.cover_image"
                            name-image="seo-post-cover"
                            @uploaded="form.cover_image = $event"
                        />
                        <label class="grid content-start gap-2">
                            <span class="text-sm font-semibold text-slate-700">Alt text ảnh {{ form.cover_image ? '*' : '' }}</span>
                            <textarea
                                v-model="form.cover_alt"
                                rows="4"
                                maxlength="255"
                                class="w-full rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                placeholder="Mô tả chính xác nội dung ảnh cho Google và người dùng trình đọc màn hình"
                            />
                            <span class="text-xs leading-5 text-slate-500">Không nhồi từ khóa; mô tả đúng hình ảnh trong một câu ngắn.</span>
                        </label>
                    </div>
                </article>

                <article class="min-w-0 rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-semibold text-slate-950">Nội dung chính</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Có thể kéo-thả ảnh trực tiếp vào trình soạn thảo; ảnh sẽ được upload và chuyển WebP.
                        </p>
                    </div>
                    <div class="min-w-0 pt-5">
                        <Editor v-model="form.content" />
                    </div>
                </article>
            </section>

            <aside class="grid gap-4 xl:sticky xl:top-24">
                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Xuất bản</h2>
                    <div class="mt-4 grid gap-4">
                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Trạng thái</span>
                            <select
                                v-model="form.status"
                                class="rounded-[10px] border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                            >
                                <option value="draft">Bản nháp</option>
                                <option value="published">Xuất bản</option>
                                <option value="scheduled">Hẹn lịch</option>
                            </select>
                        </label>
                        <label v-if="form.status === 'published'" class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Thời gian xuất bản</span>
                            <input
                                v-model="form.published_at"
                                type="datetime-local"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                            />
                            <span class="text-xs text-slate-500">Để trống để xuất bản ngay khi lưu.</span>
                        </label>
                        <label v-if="form.status === 'scheduled'" class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Thời gian hẹn lịch <span class="text-rose-500">*</span></span>
                            <input
                                v-model="form.scheduled_at"
                                type="datetime-local"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                            />
                        </label>
                    </div>
                </article>

                <article class="overflow-hidden rounded-[14px] border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950">
                            <Search class="h-4 w-4 text-violet-600" />
                            Xem trước kết quả Google
                        </h2>
                    </div>
                    <div class="p-5">
                        <p class="truncate text-xs text-emerald-700">{{ effectiveCanonicalUrl }}</p>
                        <p class="mt-1 line-clamp-2 text-xl font-medium leading-7 text-[#1a0dab]">{{ seoTitlePreview }}</p>
                        <p class="mt-1 line-clamp-3 text-sm leading-5 text-slate-600">{{ seoDescriptionPreview }}</p>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Thông tin tìm kiếm</h2>
                    <div class="mt-4 grid gap-4">
                        <label class="grid gap-2">
                            <span class="flex items-center justify-between gap-2 text-sm font-semibold text-slate-700">
                                <span>SEO title</span>
                                <span :class="seoTitlePreview.length > 65 ? 'text-rose-600' : 'text-slate-400'" class="text-xs font-normal"
                                    >{{ seoTitlePreview.length }}/65</span
                                >
                            </span>
                            <input
                                v-model="form.seo_title"
                                type="text"
                                maxlength="255"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                placeholder="Để trống sẽ dùng tiêu đề bài viết"
                            />
                        </label>
                        <label class="grid gap-2">
                            <span class="flex items-center justify-between gap-2 text-sm font-semibold text-slate-700">
                                <span>SEO description</span>
                                <span :class="seoDescriptionPreview.length > 160 ? 'text-rose-600' : 'text-slate-400'" class="text-xs font-normal"
                                    >{{ seoDescriptionPreview.length }}/160</span
                                >
                            </span>
                            <textarea
                                v-model="form.seo_description"
                                rows="4"
                                maxlength="320"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                placeholder="Để trống sẽ dùng mô tả ngắn"
                            />
                        </label>
                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Focus keyword</span>
                            <input
                                v-model="form.focus_keyword"
                                type="text"
                                maxlength="255"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                placeholder="nạp ngọc rồng online"
                            />
                        </label>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="flex items-center gap-2 text-base font-semibold text-slate-950">
                        <Link2 class="h-4 w-4 text-violet-600" /> Canonical
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-slate-500">Canonical cho Google biết URL chính thức cần được index.</p>

                    <div class="mt-4 grid grid-cols-2 gap-2 rounded-[10px] bg-slate-100 p-1">
                        <button
                            type="button"
                            class="rounded-[8px] px-3 py-2 text-xs font-semibold transition"
                            :class="canonicalMode === 'auto' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            @click="canonicalMode = 'auto'"
                        >
                            Canonical tự động
                        </button>
                        <button
                            type="button"
                            class="rounded-[8px] px-3 py-2 text-xs font-semibold transition"
                            :class="canonicalMode === 'custom' ? 'bg-white text-violet-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                            @click="canonicalMode = 'custom'"
                        >
                            Canonical tùy chỉnh
                        </button>
                    </div>

                    <div class="mt-4">
                        <div v-if="canonicalMode === 'auto'" class="rounded-[10px] border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-xs font-semibold text-emerald-700">Tự động theo slug</p>
                            <p class="mt-1 break-all text-xs leading-5 text-emerald-800">{{ generatedCanonicalUrl }}</p>
                        </div>
                        <label v-else class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">URL canonical đầy đủ</span>
                            <input
                                v-model="form.canonical_url"
                                type="url"
                                maxlength="2048"
                                class="rounded-[10px] border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                                placeholder="https://napcarot.com/danh-muc/duong-dan-chinh"
                            />
                            <span
                                v-if="canonicalUsesExternalDomain"
                                class="flex gap-2 rounded-[8px] bg-amber-50 p-2 text-xs leading-5 text-amber-700"
                            >
                                <CircleAlert class="mt-0.5 h-4 w-4 shrink-0" /> URL này thuộc domain khác; bài có thể nhường tín hiệu index cho
                                website đó.
                            </span>
                        </label>
                    </div>

                    <a
                        :href="effectiveCanonicalUrl"
                        target="_blank"
                        rel="noreferrer"
                        class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-violet-700 hover:underline"
                    >
                        Mở canonical <ExternalLink class="h-3.5 w-3.5" />
                    </a>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-base font-semibold text-slate-950">Thiết lập nâng cao</h2>
                    <div class="mt-4 grid gap-3">
                        <label class="grid gap-2">
                            <span class="text-sm font-semibold text-slate-700">Robots</span>
                            <select
                                v-model="form.robots"
                                class="rounded-[10px] border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-violet-400"
                            >
                                <option value="index,follow">Index và theo dõi liên kết</option>
                                <option value="noindex,follow">Không index, vẫn theo dõi liên kết</option>
                            </select>
                        </label>
                        <label class="flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 px-3 py-3">
                            <span
                                ><span class="block text-sm font-semibold text-slate-800">Article schema</span
                                ><span class="text-xs text-slate-500">Đánh dấu nội dung dạng bài viết.</span></span
                            >
                            <input v-model="form.article_schema" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600" />
                        </label>
                        <label class="flex items-center justify-between gap-3 rounded-[10px] border border-slate-200 px-3 py-3">
                            <span
                                ><span class="block text-sm font-semibold text-slate-800">Breadcrumb schema</span
                                ><span class="text-xs text-slate-500">Mô tả phân cấp danh mục và bài viết.</span></span
                            >
                            <input v-model="form.breadcrumb_schema" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-violet-600" />
                        </label>
                    </div>
                </article>

                <article class="rounded-[14px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-slate-950">Checklist SEO</h2>
                        <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700"
                            >{{ passedSeoChecks }}/{{ seoChecks.length }}</span
                        >
                    </div>
                    <ul class="mt-4 grid gap-2.5">
                        <li
                            v-for="item in seoChecks"
                            :key="item.label"
                            class="flex items-start gap-2 text-xs leading-5"
                            :class="item.passed ? 'text-emerald-700' : 'text-slate-500'"
                        >
                            <CheckCircle2 v-if="item.passed" class="mt-0.5 h-4 w-4 shrink-0" />
                            <CircleAlert v-else class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
                            <span>{{ item.label }}</span>
                        </li>
                    </ul>
                </article>
            </aside>
        </div>
    </div>
</template>
